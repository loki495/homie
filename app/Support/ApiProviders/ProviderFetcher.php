<?php

declare(strict_types=1);

namespace App\Support\ApiProviders;

use App\Models\CardApi;

interface ProviderFetcher
{
    /**
     * @return array{
     *     status: 'ok'|'error',
     *     summary: string,
     *     stats: list<array{label: string, value: string}>,
     *     raw: mixed,
     *     downloaded?: list<array{name: string, at: string}>,
     *     deleted?: list<array{name: string, at: string}>,
     *     current?: string,
     * }
     */
    public function fetch(CardApi $api): array;
}
