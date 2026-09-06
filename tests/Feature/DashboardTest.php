<?php

declare(strict_types=1);

use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardApi;
use App\Models\CardOutput;
use App\Models\Group;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

it('wraps api cards in a link to their url outside arrange mode', function () {
    $card = Card::factory()->create(['type' => CardType::Api, 'url' => 'http://sonarr.lan']);
    CardApi::factory()->create(['card_id' => $card->id, 'base_url' => 'http://sonarr.lan']);

    Livewire::test('dashboard')
        ->assertSeeHtml('href="http://sonarr.lan"');
});

it('shows the demo info panel, with the mock api urls, in demo mode', function () {
    config([
        'homie.demo_mode' => true,
        'homie.demo_mock_sonarr_url' => 'http://mock-sonarr',
        'homie.demo_mock_radarr_url' => 'http://mock-radarr',
    ]);

    Livewire::test('dashboard')
        ->assertSee('This is a live demo')
        ->assertSee('http://mock-sonarr')
        ->assertSee('http://mock-radarr');
});

it('hides the demo info panel outside demo mode', function () {
    config(['homie.demo_mode' => false]);

    Livewire::test('dashboard')
        ->assertDontSee('This is a live demo');
});

it('shows the sandbox command list in the demo info panel when the sandbox key is configured', function () {
    config([
        'homie.demo_mode' => true,
        'homie.demo_sandbox_ssh_private_key' => 'fake-key-contents',
    ]);

    Livewire::test('dashboard')
        ->assertSee('Demo sandbox')
        ->assertSee('uptime')
        ->assertSee('whoami');
});

it('shows a fallback message instead of the sandbox command list when the sandbox key is not configured', function () {
    config([
        'homie.demo_mode' => true,
        'homie.demo_sandbox_ssh_private_key' => null,
    ]);

    Livewire::test('dashboard')
        ->assertDontSee('Demo sandbox')
        ->assertSee('sandboxed demo target configured');
});

it('does not wrap api cards in a link while arranging', function () {
    $card = Card::factory()->create(['type' => CardType::Api, 'url' => 'http://sonarr.lan']);
    CardApi::factory()->create(['card_id' => $card->id, 'base_url' => 'http://sonarr.lan']);

    Livewire::test('dashboard')
        ->call('toggleEditing')
        ->assertDontSeeHtml('href="http://sonarr.lan"');
});

it('dims output and api cards the same way link cards dim while arranging', function () {
    $outputCard = Card::factory()->create(['type' => CardType::Output, 'name' => 'Uptime']);
    $apiCard = Card::factory()->create(['type' => CardType::Api, 'name' => 'Sonarr', 'url' => 'http://sonarr.lan']);
    CardApi::factory()->create(['card_id' => $apiCard->id, 'base_url' => 'http://sonarr.lan']);

    $component = Livewire::test('dashboard')->call('toggleEditing');

    expect(substr_count($component->html(), 'opacity-75'))->toBeGreaterThanOrEqual(2);
});

it('renders group names as real headings with an aria-expanded toggle', function () {
    Group::factory()->create(['name' => 'Media']);

    Livewire::test('dashboard')
        ->assertSeeHtml('<h2')
        ->assertSeeHtml('aria-expanded');
});

it('shows an empty-state prompt when there are no groups or cards', function () {
    Livewire::test('dashboard')->assertSee('No cards yet');
});

it('does not show the empty-state prompt once a card exists', function () {
    Card::factory()->create(['group_id' => null]);

    Livewire::test('dashboard')->assertDontSee('No cards yet');
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

it('renders an output card\'s title immediately, without waiting on its lazy-loaded content', function () {
    Process::fake(['*' => Process::result(output: 'mocked disk output', exitCode: 0)]);

    $card = Card::factory()->create(['type' => CardType::Output, 'name' => 'Uptime']);
    CardOutput::factory()->create(['card_id' => $card->id, 'command' => 'df -h']);

    Livewire::test('dashboard')
        ->assertSee('Uptime')
        ->assertDontSee('mocked disk output');
});

it('renders an api card\'s title immediately, without waiting on its lazy-loaded content', function () {
    $card = Card::factory()->create(['type' => CardType::Api, 'name' => 'Sonarr', 'url' => 'http://sonarr.lan']);
    CardApi::factory()->create(['card_id' => $card->id, 'base_url' => 'http://sonarr.lan']);

    Livewire::test('dashboard')
        ->assertSee('Sonarr')
        ->assertDontSee('HTTP');
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

it('reorders cards to match a dropped order, batched in one call', function () {
    $group = Group::factory()->create();
    $first = Card::factory()->create(['group_id' => $group->id, 'sort_order' => 0]);
    $second = Card::factory()->create(['group_id' => $group->id, 'sort_order' => 1]);
    $third = Card::factory()->create(['group_id' => $group->id, 'sort_order' => 2]);

    Livewire::test('dashboard')->call('reorderCards', [$third->id, $first->id, $second->id]);

    expect($third->fresh()->sort_order)->toBe(0)
        ->and($first->fresh()->sort_order)->toBe(1)
        ->and($second->fresh()->sort_order)->toBe(2);
});

it('reorders groups to match a dropped order, batched in one call', function () {
    $first = Group::factory()->create(['sort_order' => 0]);
    $second = Group::factory()->create(['sort_order' => 1]);
    $third = Group::factory()->create(['sort_order' => 2]);

    Livewire::test('dashboard')->call('reorderGroups', [$second->id, $third->id, $first->id]);

    expect($second->fresh()->sort_order)->toBe(0)
        ->and($third->fresh()->sort_order)->toBe(1)
        ->and($first->fresh()->sort_order)->toBe(2);
});

it('renders groups and cards as draggable only while arranging', function () {
    $group = Group::factory()->create();
    Card::factory()->create(['group_id' => $group->id]);

    $component = Livewire::test('dashboard');

    expect($component->html())->not->toContain('draggable="true"');

    $component->call('toggleEditing');

    expect($component->html())->toContain('draggable="true"');
});
