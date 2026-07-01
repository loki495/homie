<?php

declare(strict_types=1);

namespace App\Enums;

enum ApiProvider: string
{
    case Generic = 'generic';
    case Sonarr = 'sonarr';
    case Radarr = 'radarr';
    case Prowlarr = 'prowlarr';
    case Nzbget = 'nzbget';

    public function label(): string
    {
        return match ($this) {
            self::Generic => 'Generic (raw JSON)',
            self::Sonarr => 'Sonarr',
            self::Radarr => 'Radarr',
            self::Prowlarr => 'Prowlarr',
            self::Nzbget => 'NZBGet',
        };
    }
}
