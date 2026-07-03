<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\ApiProviders\BazarrFetcher;
use App\Support\ApiProviders\GenericFetcher;
use App\Support\ApiProviders\NzbgetFetcher;
use App\Support\ApiProviders\ProviderFetcher;
use App\Support\ApiProviders\ProwlarrFetcher;
use App\Support\ApiProviders\RadarrFetcher;
use App\Support\ApiProviders\SonarrFetcher;

enum ApiProvider: string
{
    case Generic = 'generic';
    case Sonarr = 'sonarr';
    case Radarr = 'radarr';
    case Prowlarr = 'prowlarr';
    case Nzbget = 'nzbget';
    case Bazarr = 'bazarr';

    public function label(): string
    {
        return match ($this) {
            self::Generic => 'Generic (raw JSON)',
            self::Sonarr => 'Sonarr',
            self::Radarr => 'Radarr',
            self::Prowlarr => 'Prowlarr',
            self::Nzbget => 'NZBGet',
            self::Bazarr => 'Bazarr',
        };
    }

    public function fetcher(): ProviderFetcher
    {
        return match ($this) {
            self::Generic => new GenericFetcher,
            self::Sonarr => new SonarrFetcher,
            self::Radarr => new RadarrFetcher,
            self::Nzbget => new NzbgetFetcher,
            self::Prowlarr => new ProwlarrFetcher,
            self::Bazarr => new BazarrFetcher,
        };
    }
}
