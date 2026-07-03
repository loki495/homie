<?php

declare(strict_types=1);

use App\Enums\DiscoveryMethod;
use App\Models\Card;
use App\Models\Machine;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
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

it('prefers the traefik host label over the raw ip:port when discovering via the docker api', function () {
    $machine = Machine::factory()->create(['host' => 'nas.lan']);

    Http::fake([
        'nas.lan:2375/containers/json' => Http::response([
            [
                'Id' => 'abc123',
                'Names' => ['/sonarr'],
                'Image' => 'linuxserver/sonarr',
                'Ports' => [['PrivatePort' => 8989, 'PublicPort' => 8989, 'Type' => 'tcp']],
                'Labels' => [
                    'traefik.enable' => 'true',
                    'traefik.http.routers.sonarr.rule' => 'Host(`sonarr.dev.local.test`)',
                ],
            ],
        ], 200),
    ]);

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('discovered')[0]['url'])->toBe('http://sonarr.dev.local.test');
});

it('surfaces a traefik-labeled container even with no published port, via the docker api', function () {
    $machine = Machine::factory()->create(['host' => 'nas.lan']);

    Http::fake([
        'nas.lan:2375/containers/json' => Http::response([
            [
                'Id' => 'abc123',
                'Names' => ['/sonarr'],
                'Image' => 'linuxserver/sonarr',
                'Ports' => [['PrivatePort' => 8989, 'Type' => 'tcp']],
                'Labels' => [
                    'traefik.enable' => 'true',
                    'traefik.http.routers.sonarr.rule' => 'Host(`sonarr.dev.local.test`)',
                ],
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
        ->and($component->get('discovered')[0]['url'])->toBe('http://sonarr.dev.local.test');
});

it('falls back to the image-declared exposed port for host-network containers, via the docker api', function () {
    $machine = Machine::factory()->create(['host' => 'nas.lan']);

    Http::fake([
        'nas.lan:2375/containers/json' => Http::response([
            [
                'Id' => 'abc123',
                'Names' => ['/homeassistant'],
                'Image' => 'ghcr.io/home-assistant/home-assistant',
                'Ports' => [],
                'HostConfig' => ['NetworkMode' => 'host'],
            ],
        ], 200),
        'nas.lan:2375/containers/abc123/json' => Http::response([
            'Config' => ['ExposedPorts' => ['8123/tcp' => new stdClass]],
        ], 200),
    ]);

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('discovered'))->toHaveCount(1)
        ->and($component->get('discovered')[0]['name'])->toBe('homeassistant')
        ->and($component->get('discovered')[0]['url'])->toBe('http://nas.lan:8123');
});

it('still surfaces host-network containers with a bare host url when the image declares no exposed port, via the docker api', function () {
    $machine = Machine::factory()->create(['host' => 'nas.lan']);

    Http::fake([
        'nas.lan:2375/containers/json' => Http::response([
            [
                'Id' => 'abc123',
                'Names' => ['/homeassistant'],
                'Image' => 'ghcr.io/home-assistant/home-assistant',
                'Ports' => [],
                'HostConfig' => ['NetworkMode' => 'host'],
            ],
        ], 200),
        'nas.lan:2375/containers/abc123/json' => Http::response([
            'Config' => ['ExposedPorts' => null],
        ], 200),
    ]);

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('discovered'))->toHaveCount(1)
        ->and($component->get('discovered')[0]['name'])->toBe('homeassistant')
        ->and($component->get('discovered')[0]['url'])->toBe('http://nas.lan');
});

it('shows a friendly error when the docker api is unreachable', function () {
    $machine = Machine::factory()->create(['host' => 'unreachable.lan']);

    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('scanError'))->toContain('Could not reach');
});

it('hands off a discovery result to the cards tab instead of creating it directly', function () {
    Livewire::test('machine-manager')
        ->call('addCardFromDiscovery', 'sonarr', 'http://nas.lan:8989')
        ->assertDispatched('switch-sidebar-tab', tab: 'cards')
        ->assertDispatched('prefill-card', name: 'sonarr', url: 'http://nas.lan:8989');

    expect(Card::where('name', 'sonarr')->exists())->toBeFalse();
});

it('creates an ssh scan target with a user and port', function () {
    Livewire::test('machine-manager')
        ->set('name', 'Local')
        ->set('host', '192.168.1.12')
        ->set('discovery_method', 'ssh')
        ->set('ssh_user', 'andres')
        ->set('ssh_port', '2222')
        ->call('save');

    $machine = Machine::where('name', 'Local')->sole();

    expect($machine->discovery_method)->toBe(DiscoveryMethod::Ssh)
        ->and($machine->ssh_user)->toBe('andres')
        ->and($machine->ssh_port)->toBe(2222);
});

it('discovers containers over ssh, surfacing only published ports', function () {
    $machine = Machine::factory()->ssh()->create(['host' => '192.168.1.12']);

    $lines = implode("\n", [
        json_encode(['Names' => 'sonarr', 'Image' => 'linuxserver/sonarr', 'Ports' => '0.0.0.0:8989->8989/tcp']),
        json_encode(['Names' => 'internal-cache', 'Image' => 'redis', 'Ports' => '6379/tcp']),
    ]);

    Process::fake([
        'ssh*' => Process::result(output: $lines, exitCode: 0),
    ]);

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('discovered'))->toHaveCount(1)
        ->and($component->get('discovered')[0]['name'])->toBe('sonarr')
        ->and($component->get('discovered')[0]['url'])->toBe('http://192.168.1.12:8989');
});

it('prefers the traefik host label over the raw ip:port when discovering over ssh', function () {
    $machine = Machine::factory()->ssh()->create(['host' => '192.168.1.12']);

    $lines = json_encode([
        'Names' => 'sonarr',
        'Image' => 'linuxserver/sonarr',
        'Ports' => '0.0.0.0:8989->8989/tcp',
        'Labels' => 'traefik.enable=true,traefik.http.routers.sonarr.rule=Host(`sonarr.dev.local.test`)',
    ]);

    Process::fake([
        'ssh*' => Process::result(output: $lines, exitCode: 0),
    ]);

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('discovered')[0]['url'])->toBe('http://sonarr.dev.local.test');
});

it('surfaces a traefik-labeled container even with no published port, over ssh', function () {
    $machine = Machine::factory()->ssh()->create(['host' => '192.168.1.12']);

    $lines = implode("\n", [
        json_encode([
            'Names' => 'sonarr',
            'Image' => 'linuxserver/sonarr',
            'Ports' => '8989/tcp',
            'Labels' => 'traefik.enable=true,traefik.http.routers.sonarr.rule=Host(`sonarr.dev.local.test`)',
        ]),
        json_encode(['Names' => 'internal-cache', 'Image' => 'redis', 'Ports' => '6379/tcp']),
    ]);

    Process::fake([
        'ssh*' => Process::result(output: $lines, exitCode: 0),
    ]);

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('discovered'))->toHaveCount(1)
        ->and($component->get('discovered')[0]['name'])->toBe('sonarr')
        ->and($component->get('discovered')[0]['url'])->toBe('http://sonarr.dev.local.test');
});

it('falls back to the image-declared exposed port for host-network containers, over ssh', function () {
    $machine = Machine::factory()->ssh()->create(['host' => '192.168.1.6']);

    $psLine = json_encode([
        'Names' => 'homeassistant',
        'Image' => 'ghcr.io/home-assistant/home-assistant',
        'Ports' => '',
        'Networks' => 'host',
    ]);
    $inspectLine = '/homeassistant::{"8123/tcp":{}}';

    Process::fake([
        'ssh*' => Process::sequence()
            ->push(Process::result(output: $psLine, exitCode: 0))
            ->push(Process::result(output: $inspectLine, exitCode: 0)),
    ]);

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('discovered'))->toHaveCount(1)
        ->and($component->get('discovered')[0]['name'])->toBe('homeassistant')
        ->and($component->get('discovered')[0]['url'])->toBe('http://192.168.1.6:8123');
});

it('still surfaces host-network containers with a bare host url when the image declares no exposed port, over ssh', function () {
    $machine = Machine::factory()->ssh()->create(['host' => '192.168.1.6']);

    $psLine = json_encode([
        'Names' => 'homeassistant',
        'Image' => 'ghcr.io/home-assistant/home-assistant',
        'Ports' => '',
        'Networks' => 'host',
    ]);
    $inspectLine = '/homeassistant::null';

    Process::fake([
        'ssh*' => Process::sequence()
            ->push(Process::result(output: $psLine, exitCode: 0))
            ->push(Process::result(output: $inspectLine, exitCode: 0)),
    ]);

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('discovered'))->toHaveCount(1)
        ->and($component->get('discovered')[0]['name'])->toBe('homeassistant')
        ->and($component->get('discovered')[0]['url'])->toBe('http://192.168.1.6');
});

it('still surfaces a host-network container even if the docker inspect lookup itself fails, over ssh', function () {
    $machine = Machine::factory()->ssh()->create(['host' => '192.168.1.6']);

    $psLine = json_encode([
        'Names' => 'homeassistant',
        'Image' => 'ghcr.io/home-assistant/home-assistant',
        'Ports' => '',
        'Networks' => 'host',
    ]);

    Process::fake([
        'ssh*' => Process::sequence()
            ->push(Process::result(output: $psLine, exitCode: 0))
            ->push(Process::result(output: '', errorOutput: 'no such container', exitCode: 1)),
    ]);

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('discovered'))->toHaveCount(1)
        ->and($component->get('discovered')[0]['name'])->toBe('homeassistant')
        ->and($component->get('discovered')[0]['url'])->toBe('http://192.168.1.6');
});

it('still surfaces a host-network container with a bare host url if the inspect lookup times out, over ssh', function () {
    $machine = Machine::factory()->ssh()->create(['host' => '192.168.1.6']);

    $psLine = json_encode([
        'Names' => 'homeassistant',
        'Image' => 'ghcr.io/home-assistant/home-assistant',
        'Ports' => '',
        'Networks' => 'host',
    ]);

    Process::fake(function ($process) use ($psLine) {
        if (str_contains((string) $process->command, 'docker ps')) {
            return Process::result(output: $psLine, exitCode: 0);
        }

        throw new RuntimeException('The process exceeded the timeout of 15 seconds.');
    });

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('discovered'))->toHaveCount(1)
        ->and($component->get('discovered')[0]['name'])->toBe('homeassistant')
        ->and($component->get('discovered')[0]['url'])->toBe('http://192.168.1.6');
});

it('does not attempt a port lookup for bridge-network containers with no port or traefik label, over ssh', function () {
    $machine = Machine::factory()->ssh()->create(['host' => '192.168.1.6']);

    $psLine = json_encode([
        'Names' => 'internal-cache',
        'Image' => 'redis',
        'Ports' => '',
        'Networks' => 'docker_default',
    ]);

    Process::fake([
        'ssh*' => Process::result(output: $psLine, exitCode: 0),
    ]);

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('discovered'))->toBe([]);
    Process::assertRanTimes(fn ($process): bool => str_starts_with((string) $process->command, 'ssh '), 1);
});

it('surfaces the ssh error output when discovery fails', function () {
    $machine = Machine::factory()->ssh()->create();

    Process::fake([
        'ssh*' => Process::result(output: '', errorOutput: 'Permission denied (publickey).', exitCode: 255),
    ]);

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('scanError'))->toContain('Permission denied (publickey).');
});

it('shows a friendly error instead of a 500 when the ssh command times out', function () {
    $machine = Machine::factory()->ssh()->create();

    Process::fake(function () {
        throw new RuntimeException('The process exceeded the timeout of 15 seconds.');
    });

    $component = Livewire::test('machine-manager')->call('discover', $machine->id);

    expect($component->get('scanError'))->toContain('SSH discovery failed');
});

it('stores the ssh private key encrypted at rest', function () {
    Livewire::test('machine-manager')
        ->set('name', 'Local')
        ->set('host', '192.168.1.12')
        ->set('discovery_method', 'ssh')
        ->set('ssh_private_key', 'super-secret-key')
        ->call('save');

    $machine = Machine::where('name', 'Local')->sole();

    expect($machine->ssh_private_key)->toBe('super-secret-key');

    $raw = DB::table('machines')->where('id', $machine->id)->value('ssh_private_key');
    expect($raw)->not->toBe('super-secret-key');
});

it('writes a temporary identity file for the scan and cleans it up afterwards', function () {
    $machine = Machine::factory()->ssh()->create([
        'ssh_private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nfakekeydata\n-----END OPENSSH PRIVATE KEY-----",
    ]);

    Process::fake([
        'ssh*' => Process::result(output: '', exitCode: 0),
    ]);

    Livewire::test('machine-manager')->call('discover', $machine->id);

    Process::assertRan(fn ($process): bool => str_contains((string) $process->command, '-i ')
        && str_contains((string) $process->command, '-o IdentitiesOnly=yes'));

    expect(glob(sys_get_temp_dir().'/homie-ssh-*'))->toBeEmpty();
});
