<?php

declare(strict_types=1);

namespace App\Support\ApiProviders;

use App\Models\CardApi;

class ProwlarrFetcher implements ProviderFetcher
{
    #[\Override]
    public function fetch(CardApi $api): array
    {
        $base = rtrim($api->base_url, '/');

        try {
            $indexers = ApiHttpClient::for($api)->get($base.'/api/v1/indexer');
            $stats = ApiHttpClient::for($api)->get($base.'/api/v1/indexerstats');

            if (! $indexers->successful() || ! $stats->successful()) {
                return [
                    'status' => 'error',
                    'summary' => 'Could not reach Prowlarr',
                    'stats' => [],
                    'raw' => null,
                ];
            }

            /** @var list<array{enable?: bool}> $indexerList */
            $indexerList = $indexers->json() ?? [];
            $enabledCount = collect($indexerList)->where('enable', true)->count();

            /** @var list<array{numberOfGrabs?: int, numberOfFailedQueries?: int, numberOfFailedGrabs?: int}> $indexerStats */
            $indexerStats = $stats->json('indexers') ?? [];
            $grabs = collect($indexerStats)->sum('numberOfGrabs');
            $failures = collect($indexerStats)->sum('numberOfFailedQueries') + collect($indexerStats)->sum('numberOfFailedGrabs');

            return [
                'status' => 'ok',
                'summary' => $enabledCount.'/'.count($indexerList).' indexers enabled',
                'stats' => [
                    ['label' => 'Indexers', 'value' => $enabledCount.'/'.count($indexerList)],
                    ['label' => 'Grabs', 'value' => (string) $grabs],
                    ['label' => 'Failures', 'value' => (string) $failures],
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
