<?php

use App\Enums\DiscoveryMethod;
use App\Models\Machine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Livewire\Component;

new class extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $host = '';

    public string $description = '';

    public string $discovery_method = 'docker';

    public string $ssh_user = '';

    public string $ssh_port = '';

    public string $ssh_private_key = '';

    public ?int $scanningMachineId = null;

    /** @var list<array{name: string, image: string, url: string}> */
    public array $discovered = [];

    public ?string $scanError = null;

    /**
     * @return array<string, string>
     */
    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'discovery_method' => 'required|in:docker,ssh',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $machine = $this->editingId ? Machine::findOrFail($this->editingId) : new Machine;

        $machine->fill([
            'name' => $this->name,
            'host' => $this->host,
            'description' => $this->description !== '' ? $this->description : null,
            'discovery_method' => DiscoveryMethod::from($this->discovery_method),
            'ssh_user' => $this->discovery_method === 'ssh' && $this->ssh_user !== '' ? $this->ssh_user : null,
            'ssh_port' => $this->discovery_method === 'ssh' && $this->ssh_port !== '' ? (int) $this->ssh_port : null,
            'ssh_private_key' => $this->discovery_method === 'ssh' && $this->ssh_private_key !== '' ? $this->ssh_private_key : null,
        ])->save();

        $this->resetForm();
    }

    public function edit(int $machineId): void
    {
        $machine = Machine::findOrFail($machineId);

        $this->editingId = $machine->id;
        $this->name = $machine->name;
        $this->host = $machine->host;
        $this->description = (string) $machine->description;
        $this->discovery_method = $machine->discovery_method->value;
        $this->ssh_user = (string) $machine->ssh_user;
        $this->ssh_port = $machine->ssh_port !== null ? (string) $machine->ssh_port : '';
        $this->ssh_private_key = (string) $machine->ssh_private_key;
        $this->dispatch('scroll-sidebar-top');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'host', 'description', 'ssh_user', 'ssh_port', 'ssh_private_key']);
        $this->discovery_method = 'docker';
        $this->resetValidation();
    }

    public function delete(int $machineId): void
    {
        Machine::findOrFail($machineId)->delete();

        if ($this->editingId === $machineId) {
            $this->resetForm();
        }
    }

    public function discover(int $machineId): void
    {
        $machine = Machine::findOrFail($machineId);

        $this->scanningMachineId = $machineId;
        $this->discovered = [];
        $this->scanError = null;

        match ($machine->discovery_method) {
            DiscoveryMethod::Docker => $this->discoverViaDocker($machine),
            DiscoveryMethod::Ssh => $this->discoverViaSsh($machine),
        };
    }

    private function discoverViaDocker(Machine $machine): void
    {
        $base = str_contains($machine->host, '://')
            ? rtrim($machine->host, '/')
            : 'http://'.$machine->host.':2375';

        try {
            $response = Http::timeout(5)->get($base.'/containers/json');

            if (! $response->successful()) {
                $this->scanError = 'Docker API returned HTTP '.$response->status().'.';

                return;
            }

            $host = parse_url($base, PHP_URL_HOST) ?: $machine->host;

            $results = [];

            foreach ($response->json() ?? [] as $container) {
                $traefikHost = $this->extractTraefikHost($container['Labels'] ?? []);
                $name = ltrim($container['Names'][0] ?? $container['Id'], '/');
                $image = $container['Image'] ?? '';

                $publicPort = collect($container['Ports'] ?? [])
                    ->pluck('PublicPort')
                    ->filter()
                    ->unique()
                    ->first();

                if ($traefikHost) {
                    $results[] = ['name' => $name, 'image' => $image, 'url' => 'http://'.$traefikHost];

                    continue;
                }

                if ($publicPort) {
                    $results[] = ['name' => $name, 'image' => $image, 'url' => 'http://'.$host.':'.$publicPort];

                    continue;
                }

                // Host-network containers never appear in `Ports` (there's no mapping
                // to publish — the container's ports are the host's ports directly), so
                // fall back to whatever port the image itself declared via EXPOSE. Still
                // surface it with just the host if the image declares no port at all —
                // it's reachable, we just don't know at which port, so leave that for
                // the user to fill in when they add the card.
                if (($container['HostConfig']['NetworkMode'] ?? null) === 'host') {
                    $port = $this->hostNetworkPortViaDockerApi($base, $container['Id']);
                    $results[] = [
                        'name' => $name,
                        'image' => $image,
                        'url' => $port ? 'http://'.$host.':'.$port : 'http://'.$host,
                    ];
                }
            }

            $this->discovered = $results;

            if ($results === []) {
                $this->scanError = 'No web-reachable containers were found (no Traefik label or published port).';
            }
        } catch (Throwable) {
            $this->scanError = 'Could not reach the Docker API at '.$base.'.';
        }
    }

    private function hostNetworkPortViaDockerApi(string $base, string $containerId): ?int
    {
        try {
            $response = Http::timeout(5)->get($base.'/containers/'.$containerId.'/json');

            if (! $response->successful()) {
                return null;
            }

            return $this->firstExposedPort($response->json('Config.ExposedPorts') ?? []);
        } catch (Throwable) {
            return null;
        }
    }

    private function discoverViaSsh(Machine $machine): void
    {
        $identityFile = null;

        if ($machine->ssh_private_key) {
            $identityFile = tempnam(sys_get_temp_dir(), 'homie-ssh-');
            file_put_contents($identityFile, rtrim($machine->ssh_private_key)."\n");
            chmod($identityFile, 0600);
        }

        try {
            $command = $this->sshCommand($machine, $identityFile, "docker ps --format '{{json .}}'");

            $result = Process::timeout(15)->run($command);

            if (! $result->successful()) {
                $stderr = trim($result->errorOutput());
                $this->scanError = $stderr !== ''
                    ? 'SSH discovery failed: '.$stderr
                    : 'SSH discovery failed (exit code '.$result->exitCode().').';

                return;
            }

            $results = [];

            /** @var array<string, string> $needsPortLookup Container name => image, for host-network containers with no published port. */
            $needsPortLookup = [];

            foreach (preg_split('/\r?\n/', trim($result->output())) as $line) {
                if ($line === '') {
                    continue;
                }

                $container = json_decode($line, true);

                if (! is_array($container)) {
                    continue;
                }

                $traefikHost = $this->extractTraefikHost($container['Labels'] ?? '');
                $port = $this->parseDockerCliPort($container['Ports'] ?? '');
                $name = $container['Names'] ?? 'unknown';
                $image = $container['Image'] ?? '';

                if ($traefikHost) {
                    $results[] = ['name' => $name, 'image' => $image, 'url' => 'http://'.$traefikHost];

                    continue;
                }

                if ($port) {
                    $results[] = ['name' => $name, 'image' => $image, 'url' => 'http://'.$machine->host.':'.$port];

                    continue;
                }

                // Host-network containers never appear with a port in `docker ps`
                // (there's no mapping to publish), so fall back to whatever port the
                // image itself declared via EXPOSE, via a follow-up `docker inspect`.
                if (($container['Networks'] ?? '') === 'host') {
                    $needsPortLookup[$name] = $image;
                }
            }

            if ($needsPortLookup !== []) {
                $this->resolveHostNetworkPortsViaSsh($machine, $identityFile, $needsPortLookup, $results);
            }

            $this->discovered = $results;

            if ($results === []) {
                $this->scanError = 'No web-reachable containers were found (no Traefik label or published port).';
            }
        } catch (Throwable $e) {
            $this->scanError = 'SSH discovery failed: '.$e->getMessage();
        } finally {
            if ($identityFile && file_exists($identityFile)) {
                unlink($identityFile);
            }
        }
    }

    /**
     * Every container in $needsPortLookup ends up in $results — with a port if the
     * image declares one via EXPOSE, otherwise a bare host URL so it still shows up in
     * discovery for the user to fill in the port manually.
     *
     * @param  array<string, string>  $needsPortLookup  Container name => image.
     * @param  list<array{name: string, image: string, url: string}>  $results  Appended to by reference.
     */
    private function resolveHostNetworkPortsViaSsh(Machine $machine, ?string $identityFile, array $needsPortLookup, array &$results): void
    {
        $resolved = [];

        $inspectTargets = implode(' ', array_map('escapeshellarg', array_keys($needsPortLookup)));
        $command = $this->sshCommand(
            $machine,
            $identityFile,
            "docker inspect {$inspectTargets} --format '{{.Name}}::{{json .Config.ExposedPorts}}'"
        );

        try {
            $result = Process::timeout(15)->run($command);
        } catch (Throwable) {
            $result = null;
        }

        if ($result?->successful()) {
            foreach (preg_split('/\r?\n/', trim($result->output())) as $line) {
                if (! str_contains($line, '::')) {
                    continue;
                }

                [$rawName, $json] = explode('::', $line, 2);
                $name = ltrim($rawName, '/');

                if (! isset($needsPortLookup[$name])) {
                    continue;
                }

                $port = $this->firstExposedPort(json_decode($json, true) ?? []);
                $resolved[$name] = true;
                $results[] = [
                    'name' => $name,
                    'image' => $needsPortLookup[$name],
                    'url' => $port ? 'http://'.$machine->host.':'.$port : 'http://'.$machine->host,
                ];
            }
        }

        foreach ($needsPortLookup as $name => $image) {
            if (isset($resolved[$name])) {
                continue;
            }

            $results[] = ['name' => $name, 'image' => $image, 'url' => 'http://'.$machine->host];
        }
    }

    /**
     * @param  array<string, mixed>  $exposedPorts  Docker's `Config.ExposedPorts` shape: {"1234/tcp": {}}.
     */
    private function firstExposedPort(array $exposedPorts): ?int
    {
        $firstKey = array_key_first($exposedPorts);

        if ($firstKey === null) {
            return null;
        }

        return (int) strtok((string) $firstKey, '/');
    }

    private function sshCommand(Machine $machine, ?string $identityFile, string $remoteCommand): string
    {
        $port = $machine->ssh_port ?: 22;
        $user = $machine->ssh_user ?: 'root';

        $options = [
            '-o BatchMode=yes',
            '-o StrictHostKeyChecking=accept-new',
            '-o UserKnownHostsFile=/dev/null',
            '-o LogLevel=ERROR',
            '-o ConnectTimeout=5',
            '-p '.$port,
        ];

        if ($identityFile) {
            $options[] = '-i '.escapeshellarg($identityFile);
            $options[] = '-o IdentitiesOnly=yes';
        }

        return sprintf(
            'ssh %s %s %s',
            implode(' ', $options),
            escapeshellarg($user.'@'.$machine->host),
            escapeshellarg($remoteCommand),
        );
    }

    private function parseDockerCliPort(string $ports): ?int
    {
        if (preg_match('/(\d+)->\d+\/(tcp|udp)/', $ports, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @param  array<string, string>|string  $labels  Docker Engine API gives an
     *                                                object of label => value;
     *                                                `docker ps --format json`
     *                                                gives a single comma-joined string.
     */
    private function extractTraefikHost(array|string $labels): ?string
    {
        $haystack = is_array($labels) ? implode(' ', $labels) : $labels;

        if (preg_match('/Host\(`([^`]+)`\)/', $haystack, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function addCardFromDiscovery(string $name, string $url): void
    {
        $this->dispatch('switch-sidebar-tab', tab: 'cards');
        $this->dispatch('prefill-card', name: $name, url: $url);
    }

    /**
     * @return Collection<int, Machine>
     */
    public function machines(): Collection
    {
        return Machine::query()->orderBy('name')->get();
    }
};
?>

<div class="space-y-6">
    <form wire:submit="save" class="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
        <flux:heading size="sm">{{ $editingId ? 'Edit scan target' : 'New scan target' }}</flux:heading>

        <flux:input wire:model="name" placeholder="Name, e.g. NAS" />
        <flux:input wire:model="host" placeholder="192.168.1.50 or nas.lan" />
        <flux:input wire:model="description" placeholder="Notes (optional)" />

        <flux:select wire:model.live="discovery_method">
            @foreach (\App\Enums\DiscoveryMethod::cases() as $method)
                <flux:select.option value="{{ $method->value }}">{{ $method->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($discovery_method === 'docker')
            <p class="text-sm text-slate-400 dark:text-slate-500">
                Assumes the Docker Engine API is reachable at <code>http://host:2375</code>. Enter a full URL in
                the field above (e.g. <code>http://host:2376</code>) if yours differs.
            </p>
        @else
            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="ssh_user" placeholder="User (default root)" />
                <flux:input wire:model="ssh_port" placeholder="Port (default 22)" />
            </div>
            <flux:textarea
                wire:model="ssh_private_key"
                rows="4"
                placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;...&#10;-----END OPENSSH PRIVATE KEY-----"
                class="font-mono"
            />
            <p class="text-sm text-slate-400 dark:text-slate-500">
                Runs <code>docker ps</code> over SSH. Key-only auth (no passwords) — paste a private key with no
                passphrase, dedicated to this purpose. Stored encrypted. Leave blank to rely on an agent already
                available to the container.
            </p>
        @endif

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary" class="flex-1">
                {{ $editingId ? 'Save' : 'Add target' }}
            </flux:button>
            @if ($editingId)
                <flux:button type="button" wire:click="cancel">Cancel</flux:button>
            @endif
        </div>
    </form>

    <ul class="space-y-2">
        @forelse ($this->machines() as $machine)
            <li class="rounded-xl border border-slate-200 p-3.5 dark:border-slate-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm text-slate-700 dark:text-slate-200">{{ $machine->name }}</p>
                        <p class="truncate text-sm text-slate-400 dark:text-slate-500">
                            {{ $machine->host }} &middot; {{ $machine->discovery_method->label() }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <flux:button
                            variant="filled"
                            size="sm"
                            wire:click="discover({{ $machine->id }})"
                        >
                            Scan
                        </flux:button>
                        <flux:button
                            icon="pencil"
                            variant="ghost"
                            size="sm"
                            wire:click="edit({{ $machine->id }})"
                            aria-label="Edit {{ $machine->name }}"
                        />
                        <flux:button
                            icon="trash"
                            variant="ghost"
                            size="sm"
                            class="!text-rose-500 hover:!text-rose-600 dark:!text-rose-400 dark:hover:!text-rose-300"
                            wire:click="delete({{ $machine->id }})"
                            wire:confirm="Delete this scan target?"
                            aria-label="Delete {{ $machine->name }}"
                        />
                    </div>
                </div>

                @if ($scanningMachineId === $machine->id)
                    <div class="mt-3 space-y-2 border-t border-slate-100 pt-3 dark:border-slate-700" wire:loading.remove wire:target="discover({{ $machine->id }})">
                        @if ($scanError)
                            <p class="text-sm text-amber-600 dark:text-amber-400">{{ $scanError }}</p>
                        @endif

                        @foreach ($discovered as $result)
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-slate-600 dark:text-slate-300">{{ $result['name'] }}</p>
                                    <p class="truncate text-xs text-slate-400 dark:text-slate-500">{{ $result['url'] }}</p>
                                </div>
                                <flux:button
                                    size="sm"
                                    wire:click="addCardFromDiscovery('{{ $result['name'] }}', '{{ $result['url'] }}')"
                                >
                                    Add card
                                </flux:button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </li>
        @empty
            <li class="text-sm text-slate-400 dark:text-slate-500">No scan targets yet.</li>
        @endforelse
    </ul>
</div>
