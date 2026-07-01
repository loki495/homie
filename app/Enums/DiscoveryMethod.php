<?php

declare(strict_types=1);

namespace App\Enums;

enum DiscoveryMethod: string
{
    case Docker = 'docker';
    case Ssh = 'ssh';

    public function label(): string
    {
        return match ($this) {
            self::Docker => 'Docker API',
            self::Ssh => 'SSH (docker ps)',
        };
    }
}
