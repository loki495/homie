<?php

declare(strict_types=1);

use App\Enums\ApiProvider;
use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardApi;
use Illuminate\Http\Client\ConnectionException;
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

it('shows an error status and summary when the api responds with a failure status', function () {
    Http::fake([
        'example.test/*' => Http::response(null, 503),
    ]);

    $card = Card::factory()->create(['type' => CardType::Api]);
    $api = CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Generic,
        'base_url' => 'https://example.test/api',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('HTTP 503')
        ->assertSeeHtml('bg-rose-500');

    expect($api->fresh())
        ->cached_data->toBeNull()
        ->last_fetched_at->not->toBeNull();
});

it('shows a friendly error instead of a 500 when the api is unreachable', function () {
    Http::fake([
        'example.test/*' => fn () => throw new ConnectionException('Could not connect'),
    ]);

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Generic,
        'base_url' => 'https://example.test/api',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Could not reach https://example.test/api')
        ->assertSeeHtml('bg-rose-500');
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

it('shows the 5 most recent downloaded and deleted files with timestamps for a sonarr card', function () {
    Http::fake([
        '*/api/v3/series' => Http::response([['id' => 1, 'title' => 'Show One']], 200),
        '*/api/v3/wanted/missing*' => Http::response(['totalRecords' => 0], 200),
        '*/api/v3/queue*' => Http::response(['totalRecords' => 0], 200),
        '*eventType=3*' => Http::response([
            'records' => [
                ['sourceTitle' => 'Show.One.S01E01.1080p.WEB-DL', 'date' => now()->subHour()->toIso8601String()],
            ],
        ], 200),
        '*eventType=5*' => Http::response([
            'records' => [
                ['sourceTitle' => 'Show One/Season 01/Show.One.S01E00.720p.HDTV.mkv', 'date' => now()->subDay()->toIso8601String()],
            ],
        ], 200),
    ]);

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Sonarr,
        'base_url' => 'http://sonarr.lan',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Recently downloaded')
        ->assertSee('Show.One.S01E01.1080p.WEB-DL')
        ->assertSee('1 hour ago')
        ->assertSee('Recently deleted')
        ->assertSee('Show.One.S01E00.720p.HDTV.mkv')
        ->assertDontSee('Show One/Season 01/Show.One.S01E00.720p.HDTV.mkv')
        ->assertSee('1 day ago');
});

it('hides the recent files sections for a sonarr card when the history endpoint fails', function () {
    Http::fake([
        '*/api/v3/series' => Http::response([['id' => 1, 'title' => 'Show One']], 200),
        '*/api/v3/wanted/missing*' => Http::response(['totalRecords' => 0], 200),
        '*/api/v3/queue*' => Http::response(['totalRecords' => 0], 200),
        '*/api/v3/history*' => Http::response(null, 500),
    ]);

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Sonarr,
        'base_url' => 'http://sonarr.lan',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Series')
        ->assertDontSee('Recently downloaded')
        ->assertDontSee('Recently deleted');
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

it('shows the 5 most recent downloaded and deleted files with timestamps for a radarr card', function () {
    Http::fake([
        '*/api/v3/movie' => Http::response([['id' => 1, 'monitored' => true, 'hasFile' => true]], 200),
        '*/api/v3/queue*' => Http::response(['totalRecords' => 0], 200),
        '*eventType=3*' => Http::response([
            'records' => [
                ['sourceTitle' => 'Movie.One.2024.1080p.WEB-DL', 'date' => now()->subHour()->toIso8601String()],
            ],
        ], 200),
        '*eventType=6*' => Http::response([
            'records' => [
                ['sourceTitle' => 'Movie Two (2023)/Movie.Two.2023.720p.HDTV.mkv', 'date' => now()->subDay()->toIso8601String()],
            ],
        ], 200),
    ]);

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Radarr,
        'base_url' => 'http://radarr.lan',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Recently downloaded')
        ->assertSee('Movie.One.2024.1080p.WEB-DL')
        ->assertSee('1 hour ago')
        ->assertSee('Recently deleted')
        ->assertSee('Movie.Two.2023.720p.HDTV.mkv')
        ->assertDontSee('Movie Two (2023)/Movie.Two.2023.720p.HDTV.mkv')
        ->assertSee('1 day ago');
});

it('hides the recent files sections for a radarr card when the history endpoint fails', function () {
    Http::fake([
        '*/api/v3/movie' => Http::response([['id' => 1, 'monitored' => true, 'hasFile' => true]], 200),
        '*/api/v3/queue*' => Http::response(['totalRecords' => 0], 200),
        '*/api/v3/history*' => Http::response(null, 500),
    ]);

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Radarr,
        'base_url' => 'http://radarr.lan',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Movies')
        ->assertDontSee('Recently downloaded')
        ->assertDontSee('Recently deleted');
});

it('shows enabled indexer count, grabs, and failures for a prowlarr card', function () {
    Http::fake([
        '*/api/v1/indexer' => Http::response([
            ['id' => 1, 'enable' => true],
            ['id' => 2, 'enable' => true],
            ['id' => 3, 'enable' => false],
        ], 200),
        '*/api/v1/indexerstats' => Http::response([
            'indexers' => [
                ['numberOfGrabs' => 40, 'numberOfFailedQueries' => 1, 'numberOfFailedGrabs' => 0],
                ['numberOfGrabs' => 5, 'numberOfFailedQueries' => 0, 'numberOfFailedGrabs' => 2],
            ],
        ], 200),
    ]);

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Prowlarr,
        'base_url' => 'http://prowlarr.lan',
        'api_key' => 'secret',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Indexers')
        ->assertSee('2/3')
        ->assertSee('Grabs')
        ->assertSee('45')
        ->assertSee('Failures')
        ->assertSee('3');

    Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'secret'));
});

it('shows missing subtitle counts for a bazarr card authenticated via query string', function () {
    Http::fake([
        '*/api/movies/wanted*' => Http::response(['total' => 5], 200),
        '*/api/episodes/wanted*' => Http::response(['total' => 12], 200),
    ]);

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Bazarr,
        'base_url' => 'http://bazarr.lan',
        'api_key' => 'secret',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Movies')
        ->assertSee('5')
        ->assertSee('Episodes')
        ->assertSee('12');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'apikey=secret'));
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

it('shows the name of the file currently downloading for an nzbget card', function () {
    Http::fake(function ($request) {
        return match ($request->data()['method'] ?? null) {
            'status' => Http::response([
                'result' => [
                    'DownloadPaused' => false,
                    'DownloadRate' => 2 * 1024 * 1024,
                    'RemainingSizeMB' => 2048,
                ],
            ], 200),
            'listgroups' => Http::response([
                'result' => [
                    ['NZBName' => 'Some.Release.Name-GROUP', 'Status' => 'PAUSED'],
                    ['NZBName' => 'Currently.Downloading.This-GROUP', 'Status' => 'DOWNLOADING'],
                ],
            ], 200),
            default => Http::response(null, 404),
        };
    });

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Nzbget,
        'base_url' => 'http://nzbget.lan:6789',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Currently.Downloading.This-GROUP')
        ->assertDontSee('Some.Release.Name-GROUP');
});

it('shows no current file for an nzbget card when nothing is actively downloading', function () {
    Http::fake(function ($request) {
        return match ($request->data()['method'] ?? null) {
            'status' => Http::response([
                'result' => [
                    'DownloadPaused' => true,
                    'DownloadRate' => 0,
                    'RemainingSizeMB' => 2048,
                ],
            ], 200),
            'listgroups' => Http::response([
                'result' => [
                    ['NZBName' => 'Some.Release.Name-GROUP', 'Status' => 'PAUSED'],
                ],
            ], 200),
            default => Http::response(null, 404),
        };
    });

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Nzbget,
        'base_url' => 'http://nzbget.lan:6789',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Paused')
        ->assertDontSee('Some.Release.Name-GROUP');
});

it('does not crash an nzbget card when the listgroups call fails', function () {
    Http::fake(function ($request) {
        return match ($request->data()['method'] ?? null) {
            'status' => Http::response([
                'result' => [
                    'DownloadPaused' => false,
                    'DownloadRate' => 2 * 1024 * 1024,
                    'RemainingSizeMB' => 2048,
                ],
            ], 200),
            'listgroups' => Http::response(null, 500),
            default => Http::response(null, 404),
        };
    });

    $card = Card::factory()->create(['type' => CardType::Api]);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Nzbget,
        'base_url' => 'http://nzbget.lan:6789',
    ]);

    Livewire::test('card-api-widget', ['card' => $card])
        ->assertSee('Downloading')
        ->assertSee('2 MB/s');
});
