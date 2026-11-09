<?php

declare(strict_types=1);

use App\Enums\ApiProvider;
use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardApi;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the dashboard in a real browser', function () {
    $page = visit('/');

    $page->assertSee('No cards yet');
});

it('renders a card once one exists', function () {
    Card::factory()->create(['group_id' => null]);

    $page = visit('/');

    $page->assertDontSee('No cards yet');
});

it('expands an api card on a tap at a phone viewport', function () {
    Http::fake(['example.test/*' => Http::response(['status' => 'up'], 200)]);

    $card = Card::factory()->create(['type' => CardType::Api, 'name' => 'Prowlarr', 'url' => 'https://example.test', 'group_id' => null]);
    CardApi::factory()->create(['card_id' => $card->id, 'provider' => ApiProvider::Generic, 'base_url' => 'https://example.test/api']);

    $page = visit('/')->on()->mobile();

    $page->assertSee('Prowlarr')
        ->assertScript("document.querySelector('[data-expanded]')", null)
        ->click('Prowlarr')
        ->assertScript("document.querySelector('[data-expanded]').getAttribute('data-expanded')", 'true')
        ->click('Prowlarr')
        ->assertScript("document.querySelector('[data-expanded]')", null);
});
