<?php

declare(strict_types=1);

use App\Enums\ApiProvider;
use App\Models\Card;
use App\Models\CardApi;
use App\Models\CardOutput;
use App\Models\Group;
use App\Models\Machine;
use App\Support\Config\ConfigExporter;

it('exports groups with their cards, nested under sort order', function () {
    $group = Group::factory()->create(['name' => 'Media', 'sort_order' => 0, 'collapsed' => true]);
    Card::factory()->create(['group_id' => $group->id, 'name' => 'Plex', 'sort_order' => 0]);

    $data = app(ConfigExporter::class)->export();

    expect($data['groups'])->toHaveCount(1)
        ->and($data['groups'][0]['name'])->toBe('Media')
        ->and($data['groups'][0]['collapsed'])->toBeTrue()
        ->and($data['groups'][0]['cards'])->toHaveCount(1)
        ->and($data['groups'][0]['cards'][0]['name'])->toBe('Plex');
});

it('exports ungrouped cards separately from grouped ones', function () {
    Card::factory()->create(['group_id' => null, 'name' => 'Router']);

    $data = app(ConfigExporter::class)->export();

    expect($data['groups'])->toBe([])
        ->and($data['ungrouped_cards'])->toHaveCount(1)
        ->and($data['ungrouped_cards'][0]['name'])->toBe('Router');
});

it('exports an output card\'s command and refresh interval', function () {
    $card = Card::factory()->output()->create(['name' => 'Uptime']);
    CardOutput::factory()->create(['card_id' => $card->id, 'command' => 'uptime -p', 'refresh_interval_seconds' => 30]);

    $data = app(ConfigExporter::class)->export();

    expect($data['ungrouped_cards'][0]['output'])->toBe([
        'command' => 'uptime -p',
        'refresh_interval_seconds' => 30,
    ]);
});

it('exports an api card\'s connection details without the secret values', function () {
    $card = Card::factory()->api()->create(['name' => 'Sonarr']);
    CardApi::factory()->create([
        'card_id' => $card->id,
        'provider' => ApiProvider::Sonarr,
        'base_url' => 'http://sonarr.lan',
        'auth_type' => 'api_key',
        'api_key' => 'super-secret',
    ]);

    $data = app(ConfigExporter::class)->export();
    $api = $data['ungrouped_cards'][0]['api'];

    expect($api['provider'])->toBe('sonarr')
        ->and($api['base_url'])->toBe('http://sonarr.lan')
        ->and($api['has_api_key'])->toBeTrue()
        ->and($api['has_password'])->toBeFalse()
        ->and(json_encode($data))->not->toContain('super-secret');
});

it('exports machines without the ssh private key value', function () {
    Machine::factory()->ssh()->create(['name' => 'NAS', 'ssh_private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nsecret\n-----END OPENSSH PRIVATE KEY-----"]);

    $data = app(ConfigExporter::class)->export();

    expect($data['machines'][0]['name'])->toBe('NAS')
        ->and($data['machines'][0]['has_ssh_private_key'])->toBeTrue()
        ->and(json_encode($data))->not->toContain('BEGIN OPENSSH PRIVATE KEY');
});

it('includes a version and exported_at timestamp', function () {
    $data = app(ConfigExporter::class)->export();

    expect($data['version'])->toBe(ConfigExporter::VERSION)
        ->and($data['exported_at'])->toBeString();
});
