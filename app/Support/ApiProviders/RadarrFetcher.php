<?php

declare(strict_types=1);

namespace App\Support\ApiProviders;

use App\Models\CardApi;
use Illuminate\Support\Carbon;

class RadarrFetcher implements ProviderFetcher
{
    /**
     * Radarr's MovieHistoryEventType enum: downloadFolderImported = 3, movieFileDeleted = 6.
     */
    private const int EVENT_TYPE_DOWNLOADED = 3;

    private const int EVENT_TYPE_DELETED = 6;

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
                'downloaded' => $this->history($api, $base, self::EVENT_TYPE_DOWNLOADED),
                'deleted' => $this->history($api, $base, self::EVENT_TYPE_DELETED, basenameOnly: true),
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

    /**
     * @return list<array{name: string, at: string}>
     */
    private function history(CardApi $api, string $base, int $eventType, bool $basenameOnly = false): array
    {
        try {
            $response = ApiHttpClient::for($api)->get($base.'/api/v3/history', [
                'page' => 1,
                'pageSize' => 5,
                'sortKey' => 'date',
                'sortDirection' => 'descending',
                'eventType' => $eventType,
            ]);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $records = $response->json('records') ?? [];

        if (! is_array($records)) {
            return [];
        }

        $history = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $name = is_string($record['sourceTitle'] ?? null) ? $record['sourceTitle'] : 'Unknown';

            $history[] = [
                'name' => $basenameOnly ? basename($name) : $name,
                'at' => is_string($record['date'] ?? null) ? Carbon::parse($record['date'])->diffForHumans() : 'Unknown',
            ];
        }

        return $history;
    }
}
