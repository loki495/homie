<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Searches the (free, no API key) homarr-labs/dashboard-icons index for
 * icons matching common self-hosted app names — lets card creation suggest
 * an icon for recognized services (sonarr, radarr, plex, ...) without
 * requiring any external credentials. Icons are hotlinked from jsDelivr's
 * CDN, never downloaded to this app.
 */
class DashboardIcons
{
    private const string INDEX_URL = 'https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/metadata.json';

    private const string BASE_URL = 'https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons';

    /**
     * @return list<array{name: string, url: string}>
     */
    public function search(string $query, int $limit = 8): array
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
}
