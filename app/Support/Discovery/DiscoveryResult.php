<?php

declare(strict_types=1);

namespace App\Support\Discovery;

final readonly class DiscoveryResult
{
    /**
     * @param  list<array{name: string, image: string, url: string}>  $containers
     */
    public function __construct(
        public array $containers,
        public ?string $error,
    ) {}
}
