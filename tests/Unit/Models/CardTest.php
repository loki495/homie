<?php

declare(strict_types=1);

use App\Enums\ApiProvider;
use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardApi;
use App\Models\CardOutput;
use App\Models\Group;
use App\Models\Machine;

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

it('can belong to multiple machines with a per-machine url override', function () {
    $card = Card::factory()->create();
    $machineA = Machine::factory()->create();
    $machineB = Machine::factory()->create();

    $card->machines()->attach($machineA->id, ['url' => 'http://a.lan']);
    $card->machines()->attach($machineB->id, ['url' => 'http://b.lan']);

    expect($card->machines)->toHaveCount(2);
    expect($card->machines->firstWhere('id', $machineA->id)->pivot->url)->toBe('http://a.lan');
    expect($card->machines->firstWhere('id', $machineB->id)->pivot->url)->toBe('http://b.lan');
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
