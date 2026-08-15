<?php

declare(strict_types=1);

use App\Models\Card;
use App\Models\Group;
use App\Models\Machine;
use Livewire\Livewire;

it('downloads a json export named with today\'s date', function () {
    Group::factory()->create(['name' => 'Media']);

    Livewire::test('backup-manager')
        ->call('export')
        ->assertFileDownloaded('homie-backup-'.now()->format('Y-m-d-His').'.json');
});

it('imports a valid backup and reports how much was imported', function () {
    Machine::factory()->create(['name' => 'Old machine']);

    $json = json_encode([
        'groups' => [],
        'ungrouped_cards' => [['name' => 'Router', 'type' => 'link']],
        'machines' => [],
    ]);

    Livewire::test('backup-manager')
        ->call('import', $json)
        ->assertSet('importError', null)
        ->assertSee('Imported 0 group(s), 1 card(s), 0 machine(s).')
        ->assertDispatched('dashboard-updated');

    expect(Card::where('name', 'Router')->exists())->toBeTrue()
        ->and(Machine::where('name', 'Old machine')->exists())->toBeFalse();
});

it('shows the re-entry warning for a card that had a secret before export', function () {
    $json = json_encode([
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

    Livewire::test('backup-manager')
        ->call('import', $json)
        ->assertSee('Card "Sonarr" needs its API key re-entered');
});

it('shows an error and leaves existing data alone when the file is not valid json', function () {
    Group::factory()->create(['name' => 'Media']);

    Livewire::test('backup-manager')
        ->call('import', '{not valid json')
        ->assertSet('importError', 'That file is not valid JSON.')
        ->assertNotDispatched('dashboard-updated');

    expect(Group::where('name', 'Media')->exists())->toBeTrue();
});

it('shows an error instead of crashing when the json is a valid but non-object value', function () {
    Livewire::test('backup-manager')
        ->call('import', '"just a string"')
        ->assertSet('importError', 'That file is not valid JSON.');
});
