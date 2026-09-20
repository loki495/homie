<?php

declare(strict_types=1);

use App\Enums\ApiProvider;
use App\Models\CardApi;
use App\Support\ApiProviders\NzbgetFetcher;
use App\Support\ApiProviders\ProwlarrFetcher;
use App\Support\ApiProviders\RadarrFetcher;
use App\Support\ApiProviders\SonarrFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

// Sad paths not already exercised via CardApiWidgetTest.php's Livewire-level
// happy-path tests: the "one of the main calls returned non-2xx" branch, the
// outer catch(Throwable) branch, and the history/listgroups malformed-shape
// guards (non-array records/groups).

it('reports unreachable when sonarr queue fails even though series succeeds', function () {
    Http::fake([
        '*/api/v3/series' => Http::response([], 200),
        '*/api/v3/queue*' => Http::response(null, 500),
        '*/api/v3/wanted/missing*' => Http::response(['totalRecords' => 0], 200),
    ]);

    $api = CardApi::factory()->make(['provider' => ApiProvider::Sonarr, 'base_url' => 'http://sonarr.lan']);

    $result = (new SonarrFetcher)->fetch($api);

    expect($result['status'])->toBe('error')
        ->and($result['summary'])->toBe('Could not reach Sonarr');
});

it('reports the connection error when sonarr is unreachable entirely', function () {
    Http::fake([
        '*/api/v3/*' => fn () => throw new ConnectionException('Could not connect'),
    ]);

    $api = CardApi::factory()->make(['provider' => ApiProvider::Sonarr, 'base_url' => 'http://sonarr.lan']);

    $result = (new SonarrFetcher)->fetch($api);

    expect($result['status'])->toBe('error')
        ->and($result['summary'])->toContain('sonarr.lan');
});

it('skips malformed sonarr history records instead of crashing', function () {
    Http::fake([
        '*/api/v3/series' => Http::response([], 200),
        '*/api/v3/queue*' => Http::response(['totalRecords' => 0], 200),
        '*/api/v3/wanted/missing*' => Http::response(['totalRecords' => 0], 200),
        '*eventType=3*' => Http::response(['records' => 'not-an-array'], 200),
        '*eventType=5*' => Http::response(['records' => [null, ['sourceTitle' => 'ok.mkv', 'date' => now()->toIso8601String()]]], 200),
    ]);

    $api = CardApi::factory()->make(['provider' => ApiProvider::Sonarr, 'base_url' => 'http://sonarr.lan']);

    $result = (new SonarrFetcher)->fetch($api);

    expect($result['downloaded'])->toBe([])
        ->and($result['deleted'])->toHaveCount(1);
});

it('reports unreachable when radarr movie list fails', function () {
    Http::fake([
        '*/api/v3/movie' => Http::response(null, 500),
        '*/api/v3/queue*' => Http::response(['totalRecords' => 0], 200),
    ]);

    $api = CardApi::factory()->make(['provider' => ApiProvider::Radarr, 'base_url' => 'http://radarr.lan']);

    $result = (new RadarrFetcher)->fetch($api);

    expect($result['status'])->toBe('error')
        ->and($result['summary'])->toBe('Could not reach Radarr');
});

it('reports the connection error when radarr is unreachable entirely', function () {
    Http::fake([
        '*/api/v3/*' => fn () => throw new ConnectionException('Could not connect'),
    ]);

    $api = CardApi::factory()->make(['provider' => ApiProvider::Radarr, 'base_url' => 'http://radarr.lan']);

    $result = (new RadarrFetcher)->fetch($api);

    expect($result['status'])->toBe('error')
        ->and($result['summary'])->toContain('radarr.lan');
});

it('skips malformed radarr history records instead of crashing', function () {
    Http::fake([
        '*/api/v3/movie' => Http::response([], 200),
        '*/api/v3/queue*' => Http::response(['totalRecords' => 0], 200),
        '*eventType=3*' => Http::response(['records' => 'not-an-array'], 200),
        '*eventType=6*' => Http::response(['records' => [null]], 200),
    ]);

    $api = CardApi::factory()->make(['provider' => ApiProvider::Radarr, 'base_url' => 'http://radarr.lan']);

    $result = (new RadarrFetcher)->fetch($api);

    expect($result['downloaded'])->toBe([])
        ->and($result['deleted'])->toBe([]);
});

it('reports unreachable when the nzbget status call fails', function () {
    Http::fake([
        '*/jsonrpc' => Http::response(null, 500),
    ]);

    $api = CardApi::factory()->make(['provider' => ApiProvider::Nzbget, 'base_url' => 'http://nzbget.lan']);

    $result = (new NzbgetFetcher)->fetch($api);

    expect($result['status'])->toBe('error')
        ->and($result['summary'])->toBe('Could not reach NZBGet');
});

it('reports the connection error when nzbget is unreachable entirely', function () {
    Http::fake([
        '*/jsonrpc' => fn () => throw new ConnectionException('Could not connect'),
    ]);

    $api = CardApi::factory()->make(['provider' => ApiProvider::Nzbget, 'base_url' => 'http://nzbget.lan']);

    $result = (new NzbgetFetcher)->fetch($api);

    expect($result['status'])->toBe('error')
        ->and($result['summary'])->toContain('nzbget.lan');
});

it('omits current when listgroups returns a malformed shape', function () {
    Http::fake([
        '*/jsonrpc' => fn ($request) => str_contains($request->body(), 'listgroups')
            ? Http::response(['result' => 'not-an-array'], 200)
            : Http::response(['result' => ['DownloadRate' => 0, 'RemainingSizeMB' => 0, 'DownloadPaused' => false]], 200),
    ]);

    $api = CardApi::factory()->make(['provider' => ApiProvider::Nzbget, 'base_url' => 'http://nzbget.lan']);

    $result = (new NzbgetFetcher)->fetch($api);

    expect($result['status'])->toBe('ok')
        ->and($result)->not->toHaveKey('current');
});

it('omits current when the listgroups call throws', function () {
    Http::fake([
        '*/jsonrpc' => fn ($request) => str_contains($request->body(), 'listgroups')
            ? throw new ConnectionException('Could not connect')
            : Http::response(['result' => ['DownloadRate' => 0, 'RemainingSizeMB' => 0, 'DownloadPaused' => false]], 200),
    ]);

    $api = CardApi::factory()->make(['provider' => ApiProvider::Nzbget, 'base_url' => 'http://nzbget.lan']);

    $result = (new NzbgetFetcher)->fetch($api);

    expect($result['status'])->toBe('ok')
        ->and($result)->not->toHaveKey('current');
});

it('reports unreachable when prowlarr indexerstats fails even though indexers succeeds', function () {
    Http::fake([
        '*/api/v1/indexer' => Http::response([], 200),
        '*/api/v1/indexerstats' => Http::response(null, 500),
    ]);

    $api = CardApi::factory()->make(['provider' => ApiProvider::Prowlarr, 'base_url' => 'http://prowlarr.lan']);

    $result = (new ProwlarrFetcher)->fetch($api);

    expect($result['status'])->toBe('error')
        ->and($result['summary'])->toBe('Could not reach Prowlarr');
});
