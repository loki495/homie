<?php

declare(strict_types=1);

use App\Enums\ApiProvider;
use App\Enums\CardType;
use App\Models\Card;
use Database\Seeders\DemoDashboardSeeder;

it('does not add the mock-api demo card outside demo mode', function () {
    config(['homie.demo_mode' => false]);

    (new DemoDashboardSeeder)->run();

    expect(Card::where('type', CardType::Api)->where('name', 'Sonarr')->exists())->toBeFalse();
});

it('seeds a real sonarr api card pointed at the mock service in demo mode', function () {
    config([
        'homie.demo_mode' => true,
        'homie.demo_mock_sonarr_url' => 'http://mock-sonarr',
    ]);

    (new DemoDashboardSeeder)->run();

    $card = Card::where('type', CardType::Api)->where('name', 'Sonarr')->sole();

    expect($card->api->provider)->toBe(ApiProvider::Sonarr)
        ->and($card->api->base_url)->toBe('http://mock-sonarr');
});
