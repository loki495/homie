<?php

declare(strict_types=1);

use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardOutput;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

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
