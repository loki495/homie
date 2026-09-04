<?php

declare(strict_types=1);

namespace App\Support\ApiProviders;

use App\Models\CardApi;
use Illuminate\Support\Carbon;

class SonarrFetcher implements ProviderFetcher
{
    /**
     * Sonarr's EpisodeHistoryEventType enum: downloadFolderImported = 3, episodeFileDeleted = 5.
     */
    private const int EVENT_TYPE_DOWNLOADED = 3;

    private const int EVENT_TYPE_DELETED = 5;

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
                'downloaded' => $this->history($api, $base, self::EVENT_TYPE_DOWNLOADED),
                'deleted' => $this->history($api, $base, self::EVENT_TYPE_DELETED, basenameOnly: true),
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => 'error',
                'summary' => ApiHttpClient::errorSummary($exception, $api),
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
