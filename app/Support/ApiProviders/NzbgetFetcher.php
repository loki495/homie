<?php

declare(strict_types=1);

namespace App\Support\ApiProviders;

use App\Models\CardApi;

class NzbgetFetcher implements ProviderFetcher
{
    #[\Override]
    public function fetch(CardApi $api): array
    {
        $base = rtrim($api->base_url, '/');

        try {
            $response = ApiHttpClient::for($api)->post($base.'/jsonrpc', ['method' => 'status']);

            if (! $response->successful()) {
                return [
                    'status' => 'error',
                    'summary' => 'Could not reach NZBGet',
                    'stats' => [],
                    'raw' => null,
                ];
            }

            /** @var array{DownloadRate?: int, RemainingSizeMB?: int, DownloadPaused?: bool} $result */
            $result = $response->json('result') ?? [];
            $paused = (bool) ($result['DownloadPaused'] ?? false);
            $rateMBs = round(($result['DownloadRate'] ?? 0) / 1024 / 1024, 1);
            $remainingGB = round(($result['RemainingSizeMB'] ?? 0) / 1024, 1);

            return [
                'status' => 'ok',
                'summary' => $paused ? 'Paused' : $rateMBs.' MB/s',
                'stats' => [
                    ['label' => 'Status', 'value' => $paused ? 'Paused' : 'Downloading'],
                    ['label' => 'Speed', 'value' => $rateMBs.' MB/s'],
                    ['label' => 'Remaining', 'value' => $remainingGB.' GB'],
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
