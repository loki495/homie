<?php

declare(strict_types=1);

use App\Support\DashboardIcons;
use Illuminate\Http\Client\ConnectionException;
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

it('caches both icon indexes so repeated searches only fetch once each', function () {
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(['plex' => ['base' => 'svg', 'aliases' => []]], 200),
        'data.jsdelivr.com/*' => Http::response(['files' => []], 200),
    ]);

    app(DashboardIcons::class)->search('plex');
    app(DashboardIcons::class)->search('plex');

    Http::assertSentCount(2);
});

it('returns an empty index gracefully when the cdn is unreachable', function () {
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(null, 500),
    ]);

    $results = app(DashboardIcons::class)->search('sonarr');

    expect($results)->toBe([]);
});

it('returns an empty index gracefully when the app-icon request throws', function () {
    Http::fake([
        'cdn.jsdelivr.net/*' => fn () => throw new ConnectionException('Could not connect'),
        'data.jsdelivr.com/*' => Http::response(['files' => []], 200),
    ]);

    $results = app(DashboardIcons::class)->search('sonarr');

    expect($results)->toBe([]);
});

/**
 * @param  list<string>  $names
 * @return array<string, mixed>
 */
function fakeHeroiconsIndex(array $names): array
{
    return [
        'files' => [
            [
                'name' => '24',
                'type' => 'directory',
                'files' => [
                    [
                        'name' => 'outline',
                        'type' => 'directory',
                        'files' => array_map(
                            fn (string $name): array => ['name' => $name.'.svg', 'type' => 'file'],
                            $names,
                        ),
                    ],
                ],
            ],
        ],
    ];
}

it('falls back to heroicons when no app icon matches', function () {
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(['sonarr' => ['base' => 'svg', 'aliases' => []]], 200),
        'data.jsdelivr.com/*' => Http::response(fakeHeroiconsIndex(['academic-cap', 'archive-box']), 200),
    ]);

    $results = app(DashboardIcons::class)->search('academic');

    expect($results)->toHaveCount(1)
        ->and($results[0]['name'])->toBe('academic-cap')
        ->and($results[0]['url'])->toBe('https://cdn.jsdelivr.net/npm/heroicons/24/outline/academic-cap.svg');
});

it('matches heroicons by a space-separated query against their hyphenated name', function () {
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response([], 200),
        'data.jsdelivr.com/*' => Http::response(fakeHeroiconsIndex(['archive-box']), 200),
    ]);

    $results = app(DashboardIcons::class)->search('archive box');

    expect($results)->toHaveCount(1)
        ->and($results[0]['name'])->toBe('archive-box');
});

it('returns app icon matches before heroicons matches', function () {
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(['router' => ['base' => 'svg', 'aliases' => []]], 200),
        'data.jsdelivr.com/*' => Http::response(fakeHeroiconsIndex(['router-simple']), 200),
    ]);

    $results = app(DashboardIcons::class)->search('router', limit: 5);

    expect($results)->toHaveCount(2)
        ->and($results[0]['name'])->toBe('router')
        ->and($results[1]['name'])->toBe('router-simple');
});

it('does not query heroicons once the app icon index already fills the limit', function () {
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response(['sonarr' => ['base' => 'svg', 'aliases' => []]], 200),
        'data.jsdelivr.com/*' => Http::response(fakeHeroiconsIndex(['academic-cap']), 200),
    ]);

    app(DashboardIcons::class)->search('sonarr', limit: 1);

    Http::assertSentCount(1);
});

it('returns an empty heroicons result gracefully when jsdelivr is unreachable', function () {
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response([], 200),
        'data.jsdelivr.com/*' => Http::response(null, 500),
    ]);

    $results = app(DashboardIcons::class)->search('academic');

    expect($results)->toBe([]);
});

it('returns an empty heroicons result gracefully when the request throws', function () {
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response([], 200),
        'data.jsdelivr.com/*' => fn () => throw new ConnectionException('Could not connect'),
    ]);

    $results = app(DashboardIcons::class)->search('academic');

    expect($results)->toBe([]);
});

it('identifies heroicons urls as monochrome and app icon urls as not', function () {
    expect(DashboardIcons::isMonochrome('https://cdn.jsdelivr.net/npm/heroicons/24/outline/academic-cap.svg'))->toBeTrue()
        ->and(DashboardIcons::isMonochrome('https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/svg/sonarr.svg'))->toBeFalse();
});
