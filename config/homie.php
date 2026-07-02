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
];
