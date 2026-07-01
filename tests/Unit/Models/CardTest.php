<?php

declare(strict_types=1);

use App\Enums\ApiProvider;
use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardApi;
use App\Models\CardOutput;
use App\Models\Group;

it('casts type to a CardType enum', function () {
    $card = Card::factory()->create(['type' => CardType::Link]);

    expect($card->fresh()->type)->toBe(CardType::Link);
});

it('belongs to a group', function () {
    $group = Group::factory()->create();
    $card = Card::factory()->create(['group_id' => $group->id]);

    expect($card->group)->toBeInstanceOf(Group::class)
        ->and($card->group->id)->toBe($group->id);
});

it('can be ungrouped', function () {
    $card = Card::factory()->create(['group_id' => null]);

    expect($card->group)->toBeNull();
});

it('has one output configuration', function () {
    $card = Card::factory()->create(['type' => CardType::Output]);
    $output = CardOutput::factory()->create(['card_id' => $card->id, 'command' => 'uptime']);

    expect($card->fresh()->output)->toBeInstanceOf(CardOutput::class)
        ->and($card->output->command)->toBe('uptime');
});

it('has one api configuration with an encrypted api key', function () {
    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Sonarr,
        'api_key' => 'super-secret',
    ]);

    $api = $card->fresh()->api;

    expect($api->provider)->toBe(ApiProvider::Sonarr)
        ->and($api->api_key)->toBe('super-secret');

    // The value stored at rest must not be the plaintext key.
    $raw = DB::table('card_apis')->where('card_id', $card->id)->value('api_key');
    expect($raw)->not->toBe('super-secret');
});
