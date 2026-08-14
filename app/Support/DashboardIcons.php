<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Searches two free, no-API-key icon indexes for card icon suggestions:
 * homarr-labs/dashboard-icons for recognized self-hosted app logos
 * (sonarr, radarr, plex, ...), and Heroicons for generic icons (a link,
 * a folder, a router, ...) when a card isn't a specific branded service.
 * App-logo matches are returned first. Icons are hotlinked from jsDelivr's
 * CDN, never downloaded to this app.
 *
 * Heroicons are monochrome (`stroke="currentColor"`), which only resolves
 * correctly when an SVG is inlined into the page — hotlinked via a plain
 * `<img>` tag it renders solid black regardless of theme. `isMonochrome()`
 * lets callers apply a `dark:invert` filter so these stay visible on dark
 * card backgrounds; homarr's full-color app logos must never get that
 * filter, so this check is keyed off the URL's host, not icon source in
 * general.
 */
class DashboardIcons
{
    private const string INDEX_URL = 'https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/metadata.json';

    private const string BASE_URL = 'https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons';

    private const string HEROICONS_INDEX_URL = 'https://data.jsdelivr.com/v1/packages/npm/heroicons@2.2.0';

    private const string HEROICONS_BASE_URL = 'https://cdn.jsdelivr.net/npm/heroicons/24/outline';

    public static function isMonochrome(string $url): bool
    {
        return str_starts_with($url, self::HEROICONS_BASE_URL);
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    public function search(string $query, int $limit = 40): array
    {
        $query = trim(strtolower($query));

        if ($query === '') {
            return [];
        }

        $matches = [];

        foreach ($this->index() as $name => $meta) {
            $haystack = strtolower($name.' '.implode(' ', $meta['aliases'] ?? []));

            if (! str_contains($haystack, $query)) {
                continue;
            }

            $matches[] = [
                'name' => $name,
                'url' => $this->iconUrl($name, $meta),
            ];

            if (count($matches) >= $limit) {
                return $matches;
            }
        }

        foreach ($this->heroicons() as $name) {
            if (! str_contains(str_replace('-', ' ', $name), $query)) {
                continue;
            }

            $matches[] = [
                'name' => $name,
                'url' => self::HEROICONS_BASE_URL.'/'.$name.'.svg',
            ];

            if (count($matches) >= $limit) {
                break;
            }
        }

        return $matches;
    }

    /**
     * @return array<string, array{base?: string, aliases?: list<string>}>
     */
    private function index(): array
    {
        return Cache::remember('dashboard-icons-index', now()->addDay(), function (): array {
            $response = Http::timeout(5)->get(self::INDEX_URL);

            if (! $response->successful()) {
                return [];
            }

            return $response->json() ?? [];
        });
    }

    /**
     * @param  array{base?: string}  $meta
     */
    private function iconUrl(string $name, array $meta): string
    {
        $format = $meta['base'] ?? 'svg';

        return self::BASE_URL.'/'.$format.'/'.$name.'.'.$format;
    }

    /**
     * @return list<string>
     */
    private function heroicons(): array
    {
        return Cache::remember('dashboard-icons-heroicons-index', now()->addDay(), function (): array {
            try {
                $response = Http::timeout(5)->get(self::HEROICONS_INDEX_URL);
            } catch (\Throwable) {
                return [];
            }

            if (! $response->successful()) {
                return [];
            }

            /** @var list<array{name: string, type: string, files?: list<array{name: string, type: string, files?: list<array{name: string, type: string}>}>}> $tree */
            $tree = $response->json('files', []);

            $outlineDir = [];

            foreach ($tree as $entry) {
                if ($entry['name'] === '24') {
                    $outlineDir = $entry['files'] ?? [];
                    break;
                }
            }

            $files = [];

            foreach ($outlineDir as $entry) {
                if ($entry['name'] === 'outline') {
                    $files = $entry['files'] ?? [];
                    break;
                }
            }

            $names = [];

            foreach ($files as $file) {
                if (str_ends_with($file['name'], '.svg')) {
                    $names[] = substr($file['name'], 0, -4);
                }
            }

            return $names;
        });
    }
}
