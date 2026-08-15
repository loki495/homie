<?php

declare(strict_types=1);

namespace App\Support\Config;

final readonly class ImportResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $groups,
        public int $cards,
        public int $machines,
        public array $warnings,
    ) {}
}
