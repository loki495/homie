<?php

declare(strict_types=1);

use App\Enums\ApiProvider;
use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardApi;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('fetches and caches data for a generic api card', function () {
    Http::fake([
        'example.test/*' => Http::response(['status' => 'up'], 200),
    ]);

    $card = Card::factory()->create(['type' => CardType::Api]);
    $api = CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Generic,
        'base_url' => 'https://example.test/api',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('HTTP 200');

    expect($api->fresh())
        ->cached_data->toBe(['status' => 'up'])
        ->last_fetched_at->not->toBeNull();
});

it('shows a not-implemented message for unsupported providers without making a request', function () {
    Http::fake();

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Sonarr,
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Sonarr integration not implemented yet.');

    Http::assertNothingSent();
});
