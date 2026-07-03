<?php

declare(strict_types=1);

namespace App\Support\ApiProviders;

use App\Models\CardApi;
use Illuminate\Support\Facades\Http;

class BazarrFetcher implements ProviderFetcher
{
    #[\Override]
    public function fetch(CardApi $api): array
    {
        $base = rtrim($api->base_url, '/');

        try {
            $query = $api->api_key ? ['apikey' => $api->api_key] : [];

            $movies = Http::timeout(5)->get($base.'/api/movies/wanted', $query);
            $episodes = Http::timeout(5)->get($base.'/api/episodes/wanted', $query);

            if (! $movies->successful() || ! $episodes->successful()) {
                return [
                    'status' => 'error',
                    'summary' => 'Could not reach Bazarr',
                    'stats' => [],
                    'raw' => null,
                ];
            }

            $missingMovies = $movies->json('total') ?? 0;
            $missingEpisodes = $episodes->json('total') ?? 0;

            return [
                'status' => 'ok',
                'summary' => ($missingMovies + $missingEpisodes).' missing subtitles',
                'stats' => [
                    ['label' => 'Movies', 'value' => (string) $missingMovies],
                    ['label' => 'Episodes', 'value' => (string) $missingEpisodes],
                ],
                'raw' => null,
            ];
        } catch (\Throwable) {
            return [
                'status' => 'error',
                'summary' => 'Could not reach '.$api->base_url,
                'stats' => [],
                'raw' => null,
            ];
        }
    }
}
