<?php

declare(strict_types=1);

use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardApi;
use App\Models\Group;
use Livewire\Livewire;

it('wraps api cards in a link to their url outside arrange mode', function () {
    $card = Card::factory()->create(['type' => CardType::Api, 'url' => 'http://sonarr.lan']);
    CardApi::factory()->create(['card_id' => $card->id, 'base_url' => 'http://sonarr.lan']);

    Livewire::test('dashboard')
        ->assertSeeHtml('href="http://sonarr.lan"');
});

it('does not wrap api cards in a link while arranging', function () {
    $card = Card::factory()->create(['type' => CardType::Api, 'url' => 'http://sonarr.lan']);
    CardApi::factory()->create(['card_id' => $card->id, 'base_url' => 'http://sonarr.lan']);

    Livewire::test('dashboard')
        ->call('toggleEditing')
        ->assertDontSeeHtml('href="http://sonarr.lan"');
});

it('renders groups with their cards and ungrouped cards', function () {
    $group = Group::factory()->create(['name' => 'Media']);
    $groupedCard = Card::factory()->create(['group_id' => $group->id, 'name' => 'Plex']);
    $ungroupedCard = Card::factory()->create(['group_id' => null, 'name' => 'Router admin']);

    Livewire::test('dashboard')
        ->assertSee('Media')
        ->assertSee($groupedCard->name)
        ->assertSee($ungroupedCard->name);
});

it('swaps sort_order with the next sibling in the same group when moving a card down', function () {
    $group = Group::factory()->create();
    $first = Card::factory()->create(['group_id' => $group->id, 'sort_order' => 0]);
    $second = Card::factory()->create(['group_id' => $group->id, 'sort_order' => 1]);

    Livewire::test('dashboard')->call('moveCard', $first->id, 1);

    expect($first->fresh()->sort_order)->toBe(1)
        ->and($second->fresh()->sort_order)->toBe(0);
});

it('does not move a card past a sibling in a different group', function () {
    $groupA = Group::factory()->create();
    $groupB = Group::factory()->create();
    $card = Card::factory()->create(['group_id' => $groupA->id, 'sort_order' => 0]);
    Card::factory()->create(['group_id' => $groupB->id, 'sort_order' => 1]);

    Livewire::test('dashboard')->call('moveCard', $card->id, 1);

    expect($card->fresh()->sort_order)->toBe(0);
});

it('toggles editing mode', function () {
    Livewire::test('dashboard')
        ->assertSet('editing', false)
        ->call('toggleEditing')
        ->assertSet('editing', true);
});

it('swaps sort_order between adjacent groups', function () {
    $first = Group::factory()->create(['sort_order' => 0]);
    $second = Group::factory()->create(['sort_order' => 1]);

    Livewire::test('dashboard')->call('moveGroup', $first->id, 1);

    expect($first->fresh()->sort_order)->toBe(1)
        ->and($second->fresh()->sort_order)->toBe(0);
});
