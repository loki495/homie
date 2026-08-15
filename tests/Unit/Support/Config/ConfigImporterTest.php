<?php

declare(strict_types=1);

use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardApi;
use App\Models\CardOutput;
use App\Models\Group;
use App\Models\Machine;
use App\Support\Config\ConfigExporter;
use App\Support\Config\ConfigImporter;

it('round-trips a full export back into matching groups, cards, and machines', function () {
    $group = Group::factory()->create(['name' => 'Media', 'sort_order' => 0]);
    $plex = Card::factory()->create(['group_id' => $group->id, 'name' => 'Plex', 'sort_order' => 0]);
    $uptime = Card::factory()->output()->create(['group_id' => null, 'name' => 'Uptime']);
    CardOutput::factory()->create(['card_id' => $uptime->id, 'command' => 'uptime -p', 'refresh_interval_seconds' => 60]);
    $sonarr = Card::factory()->api()->create(['group_id' => null, 'name' => 'Sonarr']);
    CardApi::factory()->create(['card_id' => $sonarr->id, 'base_url' => 'http://sonarr.lan']);
    Machine::factory()->create(['name' => 'NAS', 'host' => 'nas.lan']);

    $exported = app(ConfigExporter::class)->export();

    $plex->delete();
    $uptime->delete();
    $sonarr->delete();
    $group->delete();
    Machine::query()->delete();

    $result = app(ConfigImporter::class)->import($exported);

    expect($result->groups)->toBe(1)
        ->and($result->cards)->toBe(3)
        ->and($result->machines)->toBe(1)
        ->and(Group::where('name', 'Media')->exists())->toBeTrue()
        ->and(Card::where('name', 'Plex')->first()?->group?->name)->toBe('Media')
        ->and(CardOutput::whereHas('card', fn ($q) => $q->where('name', 'Uptime'))->first()?->command)->toBe('uptime -p')
        ->and(CardApi::whereHas('card', fn ($q) => $q->where('name', 'Sonarr'))->first()?->base_url)->toBe('http://sonarr.lan')
        ->and(Machine::where('name', 'NAS')->exists())->toBeTrue();
});

it('replaces existing groups, cards, and machines rather than merging', function () {
    Group::factory()->create(['name' => 'Old group']);
    Card::factory()->create(['name' => 'Old card']);
    Machine::factory()->create(['name' => 'Old machine']);

    app(ConfigImporter::class)->import([
        'groups' => [],
        'ungrouped_cards' => [['name' => 'New card', 'type' => 'link']],
        'machines' => [],
    ]);

    expect(Group::where('name', 'Old group')->exists())->toBeFalse()
        ->and(Card::where('name', 'Old card')->exists())->toBeFalse()
        ->and(Machine::where('name', 'Old machine')->exists())->toBeFalse()
        ->and(Card::where('name', 'New card')->exists())->toBeTrue();
});

it('warns that a re-entered api key is needed after import instead of importing a fake one', function () {
    $result = app(ConfigImporter::class)->import([
        'ungrouped_cards' => [[
            'name' => 'Sonarr',
            'type' => 'api',
            'api' => [
                'provider' => 'sonarr',
                'base_url' => 'http://sonarr.lan',
                'auth_type' => 'api_key',
                'has_api_key' => true,
            ],
        ]],
    ]);

    $card = Card::where('name', 'Sonarr')->first();

    expect($card?->api?->api_key)->toBeNull()
        ->and($result->warnings)->toContain('Card "Sonarr" needs its API key re-entered (not included in the export).');
});

it('skips a group missing a name instead of crashing the whole import', function () {
    $result = app(ConfigImporter::class)->import([
        'groups' => [
            ['sort_order' => 0, 'cards' => []],
            ['name' => 'Valid group', 'sort_order' => 1, 'cards' => []],
        ],
    ]);

    expect($result->groups)->toBe(1)
        ->and($result->warnings)->toContain('Skipped group at index 0: missing a name.')
        ->and(Group::where('name', 'Valid group')->exists())->toBeTrue();
});

it('skips a card with an unknown type instead of crashing the whole import', function () {
    $result = app(ConfigImporter::class)->import([
        'ungrouped_cards' => [
            ['name' => 'Bad card', 'type' => 'not-a-real-type'],
            ['name' => 'Good card', 'type' => 'link'],
        ],
    ]);

    expect($result->cards)->toBe(1)
        ->and($result->warnings)->toContain('Skipped card "Bad card": unknown card type.')
        ->and(Card::where('name', 'Good card')->exists())->toBeTrue()
        ->and(Card::where('name', 'Bad card')->exists())->toBeFalse();
});

it('skips a machine with an unknown discovery method instead of crashing the whole import', function () {
    $result = app(ConfigImporter::class)->import([
        'machines' => [
            ['name' => 'Bad machine', 'host' => 'bad.lan', 'discovery_method' => 'telepathy'],
        ],
    ]);

    expect($result->machines)->toBe(0)
        ->and($result->warnings)->toContain('Skipped machine "Bad machine": unknown discovery method.')
        ->and(Machine::where('name', 'Bad machine')->exists())->toBeFalse();
});

it('imports cleanly from an entirely empty payload without error', function () {
    $result = app(ConfigImporter::class)->import([]);

    expect($result->groups)->toBe(0)
        ->and($result->cards)->toBe(0)
        ->and($result->machines)->toBe(0)
        ->and($result->warnings)->toBe([]);
});

it('falls back to the array index for sort_order when a card omits it', function () {
    app(ConfigImporter::class)->import([
        'ungrouped_cards' => [
            ['name' => 'First', 'type' => CardType::Link->value],
            ['name' => 'Second', 'type' => CardType::Link->value],
        ],
    ]);

    expect(Card::where('name', 'First')->first()?->sort_order)->toBe(0)
        ->and(Card::where('name', 'Second')->first()?->sort_order)->toBe(1);
});
