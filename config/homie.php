<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Machine SSH key sync path
    |--------------------------------------------------------------------------
    |
    | Machine SSH keys are auto-synced here (plaintext, 0600) whenever a
    | machine is saved, so output-card commands can reference a predictable
    | path without duplicating key management. Overridden in testing so the
    | test suite never writes into the real storage/ssh directory.
    |
    */
    'ssh_key_path' => env('HOMIE_SSH_KEY_PATH', storage_path('ssh')),

    /*
    |--------------------------------------------------------------------------
    | Demo mode
    |--------------------------------------------------------------------------
    |
    | Off by default - this same image also runs the real dev/production
    | deployment, which must never be affected by any of this. When on, every
    | visitor gets their own private copy of demo_db_template_path (homie has
    | no per-user data model of its own, so this is the only way to isolate
    | concurrent public demo visitors from each other) and the whole app is
    | gated behind HTTP Basic Auth against a user seeded into that same
    | template. See ResolveDemoDatabase and RequireBasicAuthInDemoMode.
    |
    */
    'demo_mode' => env('DEMO_MODE', false),
    'demo_db_template_path' => env('DEMO_DB_TEMPLATE_PATH', storage_path('demo-template.sqlite')),
    'demo_db_storage_path' => env('DEMO_DB_STORAGE_PATH', storage_path('demo-dbs')),
    'demo_basic_auth_email' => env('DEMO_BASIC_AUTH_EMAIL', 'demo@homie.ac495.net'),
    'demo_basic_auth_password' => env('DEMO_BASIC_AUTH_PASSWORD', 'homie-demo'),

    /*
    |--------------------------------------------------------------------------
    | Demo mock arr-stack API
    |--------------------------------------------------------------------------
    |
    | Demo mode only. Two small mock services (docker-compose.yml: mock-sonarr,
    | mock-radarr - see docker/mock-arr-api/router.php) return canned, realistic-
    | shaped JSON for the Sonarr/Radarr endpoints App\Support\ApiProviders\
    | *Fetcher.php call, so a real API card pointed at one of them shows
    | plausible stats via the app's unmodified fetcher code. Defaults assume
    | the mock services are running as Docker Compose services named
    | mock-sonarr/mock-radarr on the same default network as this app
    | container - override if the demo stack's service names or ports differ.
    |
    */
    'demo_mock_sonarr_url' => env('DEMO_MOCK_SONARR_URL', 'http://mock-sonarr'),
    'demo_mock_radarr_url' => env('DEMO_MOCK_RADARR_URL', 'http://mock-radarr'),
];
