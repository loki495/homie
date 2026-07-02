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

it('authenticates with basic auth when the api uses a username and password', function () {
    Http::fake([
        'example.test/*' => Http::response(['status' => 'up'], 200),
    ]);

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Generic,
        'base_url' => 'https://example.test/api',
        'auth_type' => 'basic',
        'api_key' => null,
        'username' => 'admin',
        'password' => 'secret',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('HTTP 200');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Basic '.base64_encode('admin:secret'));
    });
});

it('picks up card name and icon changes without re-fetching the api', function () {
    Http::fake([
        'example.test/*' => Http::response(['status' => 'up'], 200),
    ]);

    $card = Card::factory()->create(['type' => CardType::Api, 'name' => 'Old Name']);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Generic,
        'base_url' => 'https://example.test/api',
    ]);

    $component = Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Old Name');

    $card->update(['name' => 'New Name', 'icon' => 'https://example.test/icon.svg']);

    $component->dispatch('dashboard-updated')
        ->assertSee('New Name')
        ->assertSee('https://example.test/icon.svg');

    Http::assertSentCount(1);
});

it('shows a not-implemented message for unsupported providers without making a request', function () {
    Http::fake();

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Prowlarr,
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Prowlarr integration not implemented yet.');

    Http::assertNothingSent();
});

it('shows series, missing, and queue counts for a sonarr card', function () {
    Http::fake([
        '*/api/v3/series' => Http::response([
            ['id' => 1, 'title' => 'Show One'],
            ['id' => 2, 'title' => 'Show Two'],
        ], 200),
        '*/api/v3/wanted/missing*' => Http::response(['totalRecords' => 4], 200),
        '*/api/v3/queue*' => Http::response(['totalRecords' => 1], 200),
    ]);

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Sonarr,
        'base_url' => 'http://sonarr.lan',
        'api_key' => 'secret',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Series')
        ->assertSee('2')
        ->assertSee('Missing')
        ->assertSee('4')
        ->assertSee('Queue')
        ->assertSee('1');

    Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'secret'));
});

it('shows movie, missing, and queue counts for a radarr card', function () {
    Http::fake([
        '*/api/v3/movie' => Http::response([
            ['id' => 1, 'monitored' => true, 'hasFile' => true],
            ['id' => 2, 'monitored' => true, 'hasFile' => false],
            ['id' => 3, 'monitored' => false, 'hasFile' => false],
        ], 200),
        '*/api/v3/queue*' => Http::response(['totalRecords' => 2], 200),
    ]);

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Radarr,
        'base_url' => 'http://radarr.lan',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Movies')
        ->assertSee('3')
        ->assertSee('Missing')
        ->assertSee('1')
        ->assertSee('Queue')
        ->assertSee('2');
});

it('shows download speed and status for an nzbget card authenticated with basic auth', function () {
    Http::fake([
        '*/jsonrpc' => Http::response([
            'result' => [
                'DownloadPaused' => false,
                'DownloadRate' => 2 * 1024 * 1024,
                'RemainingSizeMB' => 2048,
            ],
        ], 200),
    ]);

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Nzbget,
        'base_url' => 'http://nzbget.lan:6789',
        'auth_type' => 'basic',
        'api_key' => null,
        'username' => 'nzbget',
        'password' => 'tegbzn6789',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Downloading')
        ->assertSee('2 MB/s')
        ->assertSee('2 GB');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Basic '.base64_encode('nzbget:tegbzn6789'));
    });
});
