<?php

declare(strict_types=1);

/**
 * Minimal mock Sonarr/Radarr API for homie's demo mode (config('homie.demo_mode')).
 * Returns canned, realistic-shaped JSON for exactly the endpoints homie's real
 * SonarrFetcher/RadarrFetcher call (see app/Support/ApiProviders/*Fetcher.php), so a
 * real API card pointed at this container shows plausible stats through the app's
 * unmodified fetcher code. Not a full arr-stack clone - no auth, no persistence,
 * every request gets the same canned data back. Which app to imitate is picked by
 * the MOCK_PROVIDER env var (sonarr|radarr): the same script backs both the
 * mock-sonarr and mock-radarr docker-compose services, started with a different
 * env value each. Run standalone via `php -S 0.0.0.0:80 -t . router.php`.
 */
$provider = getenv('MOCK_PROVIDER') ?: 'sonarr';
$path = rtrim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
$eventType = isset($_GET['eventType']) ? (int) $_GET['eventType'] : null;
$pageSize = isset($_GET['pageSize']) ? (int) $_GET['pageSize'] : 10;

header('Content-Type: application/json');

/**
 * @param  array<mixed>  $data
 */
$respond = function (array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data);
    exit;
};

$sonarrSeries = [
    ['id' => 1, 'title' => 'Northern Lights', 'monitored' => true],
    ['id' => 2, 'title' => 'Silent Harbor', 'monitored' => true],
    ['id' => 3, 'title' => 'The Long Way Home', 'monitored' => true],
    ['id' => 4, 'title' => 'Paper Moon', 'monitored' => true],
    ['id' => 5, 'title' => 'Redwood County', 'monitored' => false],
    ['id' => 6, 'title' => 'Blue Hour', 'monitored' => true],
    ['id' => 7, 'title' => 'Late Bloom', 'monitored' => true],
    ['id' => 8, 'title' => 'The Signal', 'monitored' => true],
];

$radarrMovies = [
    ['id' => 1, 'title' => 'The Last Ferry', 'monitored' => true, 'hasFile' => true],
    ['id' => 2, 'title' => 'Glass Canyon', 'monitored' => true, 'hasFile' => true],
    ['id' => 3, 'title' => 'Midnight Freight', 'monitored' => true, 'hasFile' => false],
    ['id' => 4, 'title' => 'Paper Kites', 'monitored' => true, 'hasFile' => true],
    ['id' => 5, 'title' => 'Harbor Lights', 'monitored' => true, 'hasFile' => false],
    ['id' => 6, 'title' => 'The Quiet Room', 'monitored' => false, 'hasFile' => false],
    ['id' => 7, 'title' => 'Static and Sound', 'monitored' => true, 'hasFile' => true],
];

// Sonarr's EpisodeHistoryEventType / Radarr's MovieHistoryEventType: both assign
// downloadFolderImported = 3, but "deleted" differs (episodeFileDeleted = 5 vs
// movieFileDeleted = 6) - see this repo's own CLAUDE.md for why. Keyed the same
// way here so each provider's history endpoint filters correctly by eventType.
$historyByProvider = [
    'sonarr' => [
        3 => [
            ['sourceTitle' => 'Northern.Lights.S03E08.1080p.WEB.mkv', 'date' => gmdate('c', time() - 3600)],
            ['sourceTitle' => 'Silent.Harbor.S01E12.1080p.WEB.mkv', 'date' => gmdate('c', time() - 7200)],
            ['sourceTitle' => 'The.Long.Way.Home.S02E04.1080p.WEB.mkv', 'date' => gmdate('c', time() - 21600)],
        ],
        5 => [
            ['sourceTitle' => '/tv/Redwood County/Season 01/Redwood.County.S01E03.mkv', 'date' => gmdate('c', time() - 86400)],
        ],
    ],
    'radarr' => [
        3 => [
            ['sourceTitle' => 'The.Last.Ferry.2025.1080p.BluRay.mkv', 'date' => gmdate('c', time() - 5400)],
            ['sourceTitle' => 'Glass.Canyon.2024.1080p.WEB.mkv', 'date' => gmdate('c', time() - 18000)],
        ],
        6 => [
            ['sourceTitle' => '/movies/The Quiet Room (2023)/The.Quiet.Room.2023.mkv', 'date' => gmdate('c', time() - 172800)],
        ],
    ],
];

if ($provider === 'sonarr' && $path === '/api/v3/series') {
    $respond($sonarrSeries);
}

if ($provider === 'radarr' && $path === '/api/v3/movie') {
    $respond($radarrMovies);
}

if ($path === '/api/v3/queue') {
    $respond(['page' => 1, 'pageSize' => $pageSize, 'totalRecords' => 2, 'records' => []]);
}

if ($provider === 'sonarr' && $path === '/api/v3/wanted/missing') {
    $respond(['page' => 1, 'pageSize' => $pageSize, 'totalRecords' => 3, 'records' => []]);
}

if ($path === '/api/v3/history') {
    $records = $historyByProvider[$provider][$eventType] ?? [];
    $respond(['page' => 1, 'pageSize' => 5, 'totalRecords' => count($records), 'records' => $records]);
}

$respond(['error' => 'not found', 'path' => $path], 404);
