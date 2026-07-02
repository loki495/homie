<?php

declare(strict_types=1);

namespace App\Support\ApiProviders;

use App\Models\CardApi;

class GenericFetcher implements ProviderFetcher
{
    #[\Override]
    public function fetch(CardApi $api): array
    {
        try {
            $response = ApiHttpClient::for($api)->get($api->base_url);

            return [
                'status' => $response->successful() ? 'ok' : 'error',
                'summary' => $response->successful()
                    ? 'HTTP '.$response->status()
                    : 'HTTP '.$response->status().' — request failed',
                'stats' => [],
                'raw' => $response->successful() ? $response->json() : null,
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
