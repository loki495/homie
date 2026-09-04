<?php

declare(strict_types=1);

use App\Enums\ApiProvider;
use App\Models\Card;
use App\Models\CardApi;

it('casts provider to an ApiProvider enum', function () {
    $api = CardApi::factory()->create(['provider' => ApiProvider::Radarr]);

    expect($api->fresh()->provider)->toBe(ApiProvider::Radarr);
});

it('encrypts the password at rest', function () {
    $api = CardApi::factory()->create(['password' => 'super-secret']);

    expect($api->fresh()->password)->toBe('super-secret');

    $raw = DB::table('card_apis')->where('id', $api->id)->value('password');
    expect($raw)->not->toBe('super-secret');
});

it('belongs to a card', function () {
    $card = Card::factory()->create();
    $api = CardApi::factory()->create(['card_id' => $card->id]);

    expect($api->card)->toBeInstanceOf(Card::class)
        ->and($api->card->id)->toBe($card->id);
});
