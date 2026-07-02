<?php

declare(strict_types=1);

namespace App\Support\ApiProviders;

use App\Models\CardApi;

interface ProviderFetcher
{
    /**
     * @return array{status: 'ok'|'error', summary: string, stats: list<array{label: string, value: string}>, raw: mixed}
     */
    public function fetch(CardApi $api): array;
}
