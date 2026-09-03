<?php

declare(strict_types=1);

use App\Models\Card;
use App\Models\CardOutput;
use Illuminate\Support\Carbon;

it('casts refresh_interval_seconds and last_exit_code to integers and last_run_at to a datetime', function () {
    $output = CardOutput::factory()->create([
        'refresh_interval_seconds' => '30',
        'last_exit_code' => '0',
        'last_run_at' => now(),
    ]);

    expect($output->fresh())
        ->refresh_interval_seconds->toBeInt()
        ->last_exit_code->toBeInt()
        ->last_run_at->toBeInstanceOf(Carbon::class);
});

it('belongs to a card', function () {
    $card = Card::factory()->create();
    $output = CardOutput::factory()->create(['card_id' => $card->id]);

    expect($output->card)->toBeInstanceOf(Card::class)
        ->and($output->card->id)->toBe($card->id);
});
