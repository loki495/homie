<?php

declare(strict_types=1);

use App\Enums\ApiProvider;
use App\Support\ApiProviders\ProviderFetcher;

it('resolves every case to a working fetcher', function (ApiProvider $provider) {
    expect($provider->fetcher())->toBeInstanceOf(ProviderFetcher::class)
        ->and($provider->label())->not->toBeEmpty();
})->with(ApiProvider::cases());
