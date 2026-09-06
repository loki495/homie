<?php

declare(strict_types=1);

use App\Enums\ApiProvider;
use App\Enums\CardType;
use App\Models\Card;
use App\Models\Machine;
use Database\Seeders\DemoDashboardSeeder;
use Illuminate\Support\Str;

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

afterEach(function () {
    foreach (glob(rtrim((string) config('homie.ssh_key_path'), '/').'/*') ?: [] as $file) {
        unlink($file);
    }
});

it('does not seed the sandbox machine or card when no private key is configured', function () {
    config([
        'homie.demo_mode' => true,
        'homie.demo_sandbox_ssh_private_key' => null,
    ]);

    (new DemoDashboardSeeder)->run();

    expect(Machine::where('name', 'Demo sandbox')->exists())->toBeFalse()
        ->and(Card::where('name', 'Try: uptime')->exists())->toBeFalse();
});

it('seeds a working sandbox machine and output card when a private key is configured', function () {
    config([
        'homie.demo_mode' => true,
        'homie.demo_sandbox_host' => 'output-sandbox',
        'homie.demo_sandbox_port' => 2222,
        'homie.demo_sandbox_ssh_user' => 'sandbox',
        'homie.demo_sandbox_ssh_private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nfake\n-----END OPENSSH PRIVATE KEY-----",
    ]);

    (new DemoDashboardSeeder)->run();

    $machine = Machine::where('name', 'Demo sandbox')->sole();

    expect($machine->host)->toBe('output-sandbox')
        ->and($machine->ssh_user)->toBe('sandbox')
        ->and($machine->ssh_port)->toBe(2222)
        ->and($machine->ssh_private_key)->toContain('BEGIN OPENSSH PRIVATE KEY');

    // MachineObserver should have synced the key to storage/ssh/{slug} - the
    // same file the output card's own command string references below.
    $keyPath = rtrim((string) config('homie.ssh_key_path'), '/').'/'.Str::slug($machine->name);
    expect(file_exists($keyPath))->toBeTrue();

    $card = Card::where('name', 'Try: uptime')->sole();

    expect($card->type)->toBe(CardType::Output)
        ->and($card->output->command)->toContain('sandbox@output-sandbox')
        ->and($card->output->command)->toContain('-p 2222')
        ->and($card->output->command)->toContain($keyPath)
        ->and($card->output->command)->toEndWith('uptime');
});
