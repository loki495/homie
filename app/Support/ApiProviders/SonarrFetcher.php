<?php

declare(strict_types=1);

namespace App\Support\ApiProviders;

use App\Models\CardApi;

class SonarrFetcher implements ProviderFetcher
{
    #[\Override]
    public function fetch(CardApi $api): array
    {
        $base = rtrim($api->base_url, '/');

        try {
            $series = ApiHttpClient::for($api)->get($base.'/api/v3/series');
            $queue = ApiHttpClient::for($api)->get($base.'/api/v3/queue', ['page' => 1, 'pageSize' => 1]);
            $missing = ApiHttpClient::for($api)->get($base.'/api/v3/wanted/missing', ['page' => 1, 'pageSize' => 1]);

            if (! $series->successful() || ! $queue->successful() || ! $missing->successful()) {
                return [
                    'status' => 'error',
                    'summary' => 'Could not reach Sonarr',
                    'stats' => [],
                    'raw' => null,
                ];
            }

            $seriesCount = count($series->json() ?? []);
            $missingCount = $missing->json('totalRecords') ?? 0;
            $queueCount = $queue->json('totalRecords') ?? 0;

            return [
                'status' => 'ok',
                'summary' => $seriesCount.' series',
                'stats' => [
                    ['label' => 'Series', 'value' => (string) $seriesCount],
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
