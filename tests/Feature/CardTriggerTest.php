<?php

declare(strict_types=1);

use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardOutput;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

function triggerCard(string $command = 'echo hi'): Card
{
    $card = Card::factory()->create(['type' => CardType::Trigger, 'name' => 'Reboot Shield']);
    CardOutput::factory()->create(['card_id' => $card->id, 'command' => $command]);

    return $card;
}

it('does not run the command on mount', function () {
    Process::fake();

    Livewire::test('card-trigger-widget', ['card' => triggerCard()]);

    Process::assertNothingRan();
});

it('runs the command on click and records the result', function () {
    Process::fake(['*' => Process::result(output: "done\n", exitCode: 0)]);
    $card = triggerCard('adb reboot');

    Livewire::test('card-trigger-widget', ['card' => $card])
        ->call('run')
        ->assertSet('exitCode', 0)
        ->assertSee('done');

    Process::assertRanTimes('adb reboot', 1);
    expect($card->output->fresh()->last_exit_code)->toBe(0)
        ->and($card->output->fresh()->last_run_at)->not->toBeNull();
});

it('shows a failing exit code without throwing', function () {
    Process::fake(['*' => Process::result(errorOutput: 'no route', exitCode: 1)]);

    Livewire::test('card-trigger-widget', ['card' => triggerCard()])
        ->call('run')
        ->assertSet('exitCode', 1)
        ->assertSee('no route');
});

it('turns an exception from the process into a handled failure', function () {
    Process::fake(['*' => fn () => throw new RuntimeException('boom')]);

    Livewire::test('card-trigger-widget', ['card' => triggerCard()])
        ->call('run')
        ->assertSet('exitCode', -1)
        ->assertSee('Command failed: boom');
});

it('refuses to run in demo mode', function () {
    config(['homie.demo_mode' => true]);
    Process::fake();

    Livewire::test('card-trigger-widget', ['card' => triggerCard()])
        ->call('run')
        ->assertSet('exitCode', -1)
        ->assertSee('disabled in demo mode');

    Process::assertNothingRan();
});

it('creates a trigger card with its command and no refresh interval', function () {
    Livewire::test('card-manager')
        ->set('name', 'Reboot Shield')
        ->set('type', 'trigger')
        ->set('command', 'adb reboot')
        ->set('refreshAmount', 5)
        ->call('save')
        ->assertHasNoErrors();

    $card = Card::firstWhere('name', 'Reboot Shield');

    expect($card->type)->toBe(CardType::Trigger)
        ->and($card->output->command)->toBe('adb reboot')
        ->and($card->output->refresh_interval_seconds)->toBeNull();
});

it('requires a command for a trigger card', function () {
    Livewire::test('card-manager')
        ->set('name', 'Empty')
        ->set('type', 'trigger')
        ->call('save')
        ->assertHasErrors(['command' => 'required']);
});

it('rejects creating a trigger card in demo mode', function () {
    config(['homie.demo_mode' => true]);

    Livewire::test('card-manager')
        ->set('name', 'Nope')
        ->set('type', 'trigger')
        ->set('command', 'uptime')
        ->call('save')
        ->assertHasErrors(['command']);

    expect(Card::count())->toBe(0);
});

it('renders a Run button for a trigger card on the dashboard without executing it', function () {
    Process::fake();
    triggerCard();

    Livewire::test('dashboard')->assertSee('Reboot Shield');

    Process::assertNothingRan();
});
