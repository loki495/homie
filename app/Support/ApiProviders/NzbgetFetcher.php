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

            $current = $this->currentlyDownloading($api, $base);

            return [
                'status' => 'ok',
                'summary' => $paused ? 'Paused' : $rateMBs.' MB/s',
                'stats' => [
                    ['label' => 'Status', 'value' => $paused ? 'Paused' : 'Downloading'],
                    ['label' => 'Speed', 'value' => $rateMBs.' MB/s'],
                    ['label' => 'Remaining', 'value' => $remainingGB.' GB'],
                ],
                'raw' => null,
                ...($current !== null ? ['current' => $current] : []),
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

    private function currentlyDownloading(CardApi $api, string $base): ?string
    {
        try {
            $response = ApiHttpClient::for($api)->post($base.'/jsonrpc', ['method' => 'listgroups', 'params' => [0]]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $groups = $response->json('result') ?? [];

        if (! is_array($groups)) {
            return null;
        }

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            if (($group['Status'] ?? null) === 'DOWNLOADING') {
                $name = $group['NZBName'] ?? null;

                return is_string($name) ? $name : null;
            }
        }

        return null;
    }
}
