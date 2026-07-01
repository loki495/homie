<?php

declare(strict_types=1);

use App\Support\DashboardIcons;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Cache::flush());

it('matches icons by name or alias and builds the correct cdn url', function () {
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response([
            'sonarr' => ['base' => 'svg', 'aliases' => []],
            'radarr' => ['base' => 'png', 'aliases' => ['radar']],
            'plex' => ['base' => 'svg', 'aliases' => []],
        ], 200),
    ]);

    $results = app(DashboardIcons::class)->search('sonarr');

    expect($results)->toHaveCount(1)
        ->and($results[0]['name'])->toBe('sonarr')
        ->and($results[0]['url'])->toBe('https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/svg/sonarr.svg');
});

it('matches on alias and respects the icon format from metadata', function () {
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response([
            'radarr' => ['base' => 'png', 'aliases' => ['radar']],
        ], 200),
    ]);

    $results = app(DashboardIcons::class)->search('radar');

    expect($results)->toHaveCount(1)
        ->and($results[0]['url'])->toBe('https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/png/radarr.png');
});

it('returns no results for a blank query without making a request', function () {
    Http::fake();

    $results = app(DashboardIcons::class)->search('');

    expect($results)->toBe([]);
    Http::assertNothingSent();
});

it('caches the index so repeated searches only fetch once', function () {
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(['plex' => ['base' => 'svg', 'aliases' => []]], 200),
    ]);

    app(DashboardIcons::class)->search('plex');
    app(DashboardIcons::class)->search('plex');

    Http::assertSentCount(1);
});

it('returns an empty index gracefully when the cdn is unreachable', function () {
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(null, 500),
    ]);

    $results = app(DashboardIcons::class)->search('sonarr');

    expect($results)->toBe([]);
});
