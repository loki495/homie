<?php

declare(strict_types=1);

use App\Enums\CardType;
use App\Models\Card;
use App\Models\Machine;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('creates a scan target', function () {
    Livewire::test('machine-manager')
        ->set('name', 'NAS')
        ->set('host', 'nas.lan')
        ->call('save');

    expect(Machine::where('name', 'NAS')->where('host', 'nas.lan')->exists())->toBeTrue();
});

it('edits a scan target', function () {
    $machine = Machine::factory()->create();

    Livewire::test('machine-manager')
        ->call('edit', $machine->id)
        ->set('host', 'updated.lan')
        ->call('save');

    expect($machine->fresh()->host)->toBe('updated.lan');
});

it('deletes a scan target', function () {
    $machine = Machine::factory()->create();

    Livewire::test('machine-manager')->call('delete', $machine->id);

    expect(Machine::find($machine->id))->toBeNull();
});

it('only surfaces containers with a published port when discovering', function () {
    $machine = Machine::factory()->create(['host' => 'nas.lan']);

    Http::fake([
        'nas.lan:2375/containers/json' => Http::response([
            [
                'Id' => 'abc123',
                'Names' => ['/sonarr'],
                'Image' => 'linuxserver/sonarr',
                'Ports' => [['PrivatePort' => 8989, 'PublicPort' => 8989, 'Type' => 'tcp']],
            ],
            [
                'Id' => 'def456',
                'Names' => ['/internal-cache'],
                'Image' => 'redis',
                'Ports' => [['PrivatePort' => 6379, 'Type' => 'tcp']],
            ],
        ], 200),
    ]);

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('discovered'))->toHaveCount(1)
        ->and($component->get('discovered')[0]['name'])->toBe('sonarr')
        ->and($component->get('discovered')[0]['url'])->toBe('http://nas.lan:8989');
});

it('shows a friendly error when the docker api is unreachable', function () {
    $machine = Machine::factory()->create(['host' => 'unreachable.lan']);

    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('scanError'))->toContain('Could not reach');
});

it('creates a link card from a discovery result', function () {
    Livewire::test('machine-manager')
        ->call('addCardFromDiscovery', 'sonarr', 'http://nas.lan:8989');

    $card = Card::where('name', 'sonarr')->sole();

    expect($card->type)->toBe(CardType::Link)
        ->and($card->url)->toBe('http://nas.lan:8989');
});
