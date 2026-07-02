<?php

declare(strict_types=1);

namespace App\Support\ApiProviders;

use App\Models\CardApi;

class RadarrFetcher implements ProviderFetcher
{
    #[\Override]
    public function fetch(CardApi $api): array
    {
        $base = rtrim($api->base_url, '/');

        try {
            $movies = ApiHttpClient::for($api)->get($base.'/api/v3/movie');
            $queue = ApiHttpClient::for($api)->get($base.'/api/v3/queue', ['page' => 1, 'pageSize' => 1]);

            if (! $movies->successful() || ! $queue->successful()) {
                return [
                    'status' => 'error',
                    'summary' => 'Could not reach Radarr',
                    'stats' => [],
                    'raw' => null,
                ];
            }

            /** @var list<array{monitored?: bool, hasFile?: bool}> $movieList */
            $movieList = $movies->json() ?? [];
            $missingCount = collect($movieList)
                ->filter(fn (array $movie) => ($movie['monitored'] ?? false) && ! ($movie['hasFile'] ?? false))
                ->count();
            $queueCount = $queue->json('totalRecords') ?? 0;

            return [
                'status' => 'ok',
                'summary' => count($movieList).' movies',
                'stats' => [
                    ['label' => 'Movies', 'value' => (string) count($movieList)],
                    ['label' => 'Missing', 'value' => (string) $missingCount],
                    ['label' => 'Queue', 'value' => (string) $queueCount],
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
