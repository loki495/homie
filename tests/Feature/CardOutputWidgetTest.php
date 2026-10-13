<?php

declare(strict_types=1);

use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardOutput;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

it('skips re-running the command when a poll is already in flight for the same card output', function () {
    Process::fake([
        '*' => Process::result(output: 'mocked disk output', exitCode: 0),
    ]);

    $card = Card::factory()->create(['type' => CardType::Output]);
    $cardOutput = CardOutput::factory()->create([
        'card_id' => $card->id,
        'command' => 'df -h',
        'last_output' => 'previous output',
        'last_exit_code' => 0,
    ]);

    $lock = Cache::lock("card-output-running:{$cardOutput->id}", 15);
    $lock->get();

    Livewire::test('card-output-widget', ['card' => $card])
        ->assertSee('previous output');

    Process::assertNotRan('df -h');

    $lock->release();
});

it('releases its lock after a run so the next poll is not blocked', function () {
    Process::fake([
        '*' => Process::result(output: 'mocked disk output', exitCode: 0),
    ]);

    $card = Card::factory()->create(['type' => CardType::Output]);
    CardOutput::factory()->create(['card_id' => $card->id, 'command' => 'df -h']);

    Livewire::test('card-output-widget', ['card' => $card])
        ->call('refreshOutput');

    Process::assertRanTimes('df -h', 2);
});

it('runs the configured command and displays its output', function () {
    Process::fake([
        '*' => Process::result(output: "mocked disk output\n", exitCode: 0),
    ]);

    $card = Card::factory()->create(['type' => CardType::Output]);
    $cardOutput = CardOutput::factory()->create(['card_id' => $card->id, 'command' => 'df -h']);

    Livewire::test('card-output-widget', ['card' => $card])
        ->assertSee('mocked disk output');

    expect($cardOutput->fresh())
        ->last_output->toBe('mocked disk output')
        ->last_exit_code->toBe(0)
        ->last_run_at->not->toBeNull();
});

it('shows the error output when the command fails', function () {
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'command not found', exitCode: 127),
    ]);

    $card = Card::factory()->create(['type' => CardType::Output]);
    CardOutput::factory()->create(['card_id' => $card->id, 'command' => 'not-a-real-command']);

    Livewire::test('card-output-widget', ['card' => $card])
        ->assertSee('command not found');
});

it('shows a friendly message instead of a 500 error when the command times out or fails to run', function () {
    Process::fake(function () {
        throw new RuntimeException('The process exceeded the timeout of 10 seconds.');
    });

    $card = Card::factory()->create(['type' => CardType::Output]);
    $cardOutput = CardOutput::factory()->create(['card_id' => $card->id, 'command' => 'sleep 20']);

    Livewire::test('card-output-widget', ['card' => $card])
        ->assertSee('Command failed');

    expect($cardOutput->fresh())
        ->last_exit_code->toBe(-1)
        ->last_run_at->not->toBeNull();
});

it('renders a wire:poll attribute at the configured refresh interval', function () {
    Process::fake([
        '*' => Process::result(output: "mocked disk output\n", exitCode: 0),
    ]);

    $card = Card::factory()->create(['type' => CardType::Output]);
    CardOutput::factory()->create([
        'card_id' => $card->id,
        'command' => 'df -h',
        'refresh_interval_seconds' => 30,
    ]);

    Livewire::test('card-output-widget', ['card' => $card])
        ->assertSeeHtml('wire:poll.30s="refreshOutput"');
});

it('does not render a wire:poll attribute when no refresh interval is configured', function () {
    Process::fake([
        '*' => Process::result(output: "mocked disk output\n", exitCode: 0),
    ]);

    $card = Card::factory()->create(['type' => CardType::Output]);
    CardOutput::factory()->create(['card_id' => $card->id, 'command' => 'df -h']);

    Livewire::test('card-output-widget', ['card' => $card])
        ->assertDontSeeHtml('wire:poll');
});

it('re-runs the command each time refreshOutput is polled', function () {
    Process::fake([
        '*' => Process::result(output: "mocked disk output\n", exitCode: 0),
    ]);

    $card = Card::factory()->create(['type' => CardType::Output]);
    CardOutput::factory()->create([
        'card_id' => $card->id,
        'command' => 'df -h',
        'refresh_interval_seconds' => 30,
    ]);

    Livewire::test('card-output-widget', ['card' => $card])
        ->call('refreshOutput')
        ->call('refreshOutput');

    Process::assertRanTimes('df -h', 3);
});

it('picks up a changed refresh interval without re-running the command', function () {
    Process::fake([
        '*' => Process::result(output: "mocked disk output\n", exitCode: 0),
    ]);

    $card = Card::factory()->create(['type' => CardType::Output]);
    $cardOutput = CardOutput::factory()->create([
        'card_id' => $card->id,
        'command' => 'df -h',
        'refresh_interval_seconds' => 30,
    ]);

    $component = Livewire::test('card-output-widget', ['card' => $card])
        ->assertSeeHtml('wire:poll.30s="refreshOutput"');

    $cardOutput->update(['refresh_interval_seconds' => 60]);

    $component->dispatch('dashboard-updated')
        ->assertSeeHtml('wire:poll.60s="refreshOutput"');

    Process::assertRanTimes('df -h', 1);
});

it('in demo mode runs an allowed command only over ssh on the sandbox', function () {
    config([
        'homie.demo_mode' => true,
        'homie.demo_sandbox_host' => 'output-sandbox',
        'homie.demo_sandbox_port' => 2222,
        'homie.demo_sandbox_ssh_user' => 'sandbox',
    ]);
    Process::fake(['*' => Process::result(output: 'up 1 day', exitCode: 0)]);

    $card = Card::factory()->create(['type' => CardType::Output]);
    CardOutput::factory()->create(['card_id' => $card->id, 'command' => 'uptime']);

    Livewire::test('card-output-widget', ['card' => $card])->assertSee('up 1 day');

    Process::assertRan(fn ($process) => str_starts_with($process->command, 'ssh ')
        && str_ends_with($process->command, "'sandbox@output-sandbox' 'uptime'"));
});

it('in demo mode refuses a stored command that is not on the allowlist and never runs it', function (string $command) {
    config(['homie.demo_mode' => true]);
    Process::fake();

    $card = Card::factory()->create(['type' => CardType::Output]);
    $cardOutput = CardOutput::factory()->create(['card_id' => $card->id, 'command' => $command]);

    Livewire::test('card-output-widget', ['card' => $card])
        ->assertSee('Command not allowed in demo mode.');

    Process::assertNothingRan();
    expect($cardOutput->fresh()->last_run_at)->toBeNull();
})->with(['cat .env', 'id; cat .env', 'uptime && id']);

it('outside demo mode still runs the command as typed', function () {
    config(['homie.demo_mode' => false]);
    Process::fake(['*' => Process::result(output: 'ok', exitCode: 0)]);

    $card = Card::factory()->create(['type' => CardType::Output]);
    CardOutput::factory()->create(['card_id' => $card->id, 'command' => 'cat /etc/hostname']);

    Livewire::test('card-output-widget', ['card' => $card])->assertSee('ok');

    Process::assertRan('cat /etc/hostname');
});
