<?php

declare(strict_types=1);

namespace App\Support\ApiProviders;

use App\Models\CardApi;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ApiHttpClient
{
    public static function for(CardApi $api): PendingRequest
    {
        return Http::timeout(5)
            ->when(
                $api->auth_type === 'basic' && $api->username,
                fn (PendingRequest $http) => $http->withBasicAuth($api->username, $api->password ?? '')
            )
            ->when(
                $api->auth_type !== 'basic' && $api->api_key,
                fn (PendingRequest $http) => $http->withHeader('X-Api-Key', $api->api_key)
            );
    }
}
