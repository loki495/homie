<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Demo mode only: an API card's base URL is fetched server-side from the app
 * container, so a visitor-chosen URL would be a blind SSRF / port-scan oracle
 * against whatever the demo host can reach. Only the mock Sonarr/Radarr
 * services are allowed.
 */
final class DemoApiUrl
{
    public static function isAllowed(string $url): bool
    {
        $allowed = array_map(
            static fn (string $key): string => rtrim((string) config($key), '/'),
            ['homie.demo_mock_sonarr_url', 'homie.demo_mock_radarr_url'],
        );

        return in_array(rtrim($url, '/'), $allowed, true);
    }
}
