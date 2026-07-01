<?php

use App\Enums\CardType;
use App\Enums\DiscoveryMethod;
use App\Models\Card;
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

    public ?int $scanningMachineId = null;

    /** @var list<array{name: string, image: string, port: int, url: string}> */
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
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'host', 'description', 'ssh_user', 'ssh_port']);
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
                $publicPort = collect($container['Ports'] ?? [])
                    ->pluck('PublicPort')
                    ->filter()
                    ->unique()
                    ->first();

                if (! $publicPort) {
                    continue;
                }

                $results[] = [
                    'name' => ltrim($container['Names'][0] ?? $container['Id'], '/'),
                    'image' => $container['Image'] ?? '',
                    'port' => $publicPort,
                    'url' => 'http://'.$host.':'.$publicPort,
                ];
            }

            $this->discovered = $results;

            if ($results === []) {
                $this->scanError = 'No containers with published ports were found.';
            }
        } catch (Throwable) {
            $this->scanError = 'Could not reach the Docker API at '.$base.'.';
        }
    }

    private function discoverViaSsh(Machine $machine): void
    {
        $command = $this->sshCommand($machine, "docker ps --format '{{json .}}'");

        $result = Process::timeout(15)->run($command);

        if (! $result->successful()) {
            $stderr = trim($result->errorOutput());
            $this->scanError = $stderr !== ''
                ? 'SSH discovery failed: '.$stderr
                : 'SSH discovery failed (exit code '.$result->exitCode().').';

            return;
        }

        $results = [];

        foreach (preg_split('/\r?\n/', trim($result->output())) as $line) {
            if ($line === '') {
                continue;
            }

            $container = json_decode($line, true);

            if (! is_array($container)) {
                continue;
            }

            $port = $this->parseDockerCliPort($container['Ports'] ?? '');

            if (! $port) {
                continue;
            }

            $results[] = [
                'name' => $container['Names'] ?? 'unknown',
                'image' => $container['Image'] ?? '',
                'port' => $port,
                'url' => 'http://'.$machine->host.':'.$port,
            ];
        }

        $this->discovered = $results;

        if ($results === []) {
            $this->scanError = 'No containers with published ports were found.';
        }
    }

    private function sshCommand(Machine $machine, string $remoteCommand): string
    {
        $port = $machine->ssh_port ?: 22;
        $user = $machine->ssh_user ?: 'root';
        $identity = storage_path('ssh/id_rsa');

        $options = [
            '-o BatchMode=yes',
            '-o StrictHostKeyChecking=accept-new',
            '-o UserKnownHostsFile=/dev/null',
            '-o LogLevel=ERROR',
            '-o ConnectTimeout=5',
            '-p '.$port,
        ];

        if (is_file($identity)) {
            $options[] = '-i '.escapeshellarg($identity);
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

    public function addCardFromDiscovery(string $name, string $url): void
    {
        Card::create([
            'name' => $name,
            'type' => CardType::Link,
            'url' => $url,
            'sort_order' => (Card::whereNull('group_id')->max('sort_order') ?? -1) + 1,
        ]);

        $this->dispatch('dashboard-updated');
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
        <label class="block text-sm font-medium text-slate-500 dark:text-slate-400">
            {{ $editingId ? 'Edit scan target' : 'New scan target' }}
        </label>

        <input
            type="text"
            wire:model="name"
            placeholder="Name, e.g. NAS"
            class="w-full rounded-lg border-slate-300 px-3.5 py-3 text-base sm:text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400"
        >
        @error('name')
            <p class="text-sm text-rose-500">{{ $message }}</p>
        @enderror

        <input
            type="text"
            wire:model="host"
            placeholder="192.168.1.50 or nas.lan"
            class="w-full rounded-lg border-slate-300 px-3.5 py-3 text-base sm:text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400"
        >
        @error('host')
            <p class="text-sm text-rose-500">{{ $message }}</p>
        @enderror

        <input
            type="text"
            wire:model="description"
            placeholder="Notes (optional)"
            class="w-full rounded-lg border-slate-300 px-3.5 py-3 text-base sm:text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400"
        >

        <select
            wire:model.live="discovery_method"
            class="w-full rounded-lg border-slate-300 px-3.5 py-3 text-base sm:text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
        >
            @foreach (\App\Enums\DiscoveryMethod::cases() as $method)
                <option value="{{ $method->value }}">{{ $method->label() }}</option>
            @endforeach
        </select>

        @if ($discovery_method === 'docker')
            <p class="text-sm text-slate-400 dark:text-slate-500">
                Assumes the Docker Engine API is reachable at <code>http://host:2375</code>. Enter a full URL in
                the field above (e.g. <code>http://host:2376</code>) if yours differs.
            </p>
        @else
            <div class="grid grid-cols-2 gap-3">
                <input
                    type="text"
                    wire:model="ssh_user"
                    placeholder="User (default root)"
                    class="w-full rounded-lg border-slate-300 px-3.5 py-3 text-base sm:text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400"
                >
                <input
                    type="text"
                    wire:model="ssh_port"
                    placeholder="Port (default 22)"
                    class="w-full rounded-lg border-slate-300 px-3.5 py-3 text-base sm:text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400"
                >
            </div>
            @error('ssh_port')
                <p class="text-sm text-rose-500">{{ $message }}</p>
            @enderror
            <p class="text-sm text-slate-400 dark:text-slate-500">
                Runs <code>docker ps</code> over SSH. Uses key-based auth only — place a private key at
                <code>storage/ssh/id_rsa</code> or rely on an agent already available to the container.
            </p>
        @endif

        <div class="flex gap-2">
            <button
                type="submit"
                class="flex-1 rounded-lg bg-slate-800 px-4 py-3 text-sm font-semibold text-white active:bg-slate-700 dark:bg-slate-100 dark:text-slate-800 dark:active:bg-slate-200"
            >
                {{ $editingId ? 'Save' : 'Add target' }}
            </button>
            @if ($editingId)
                <button
                    type="button"
                    wire:click="cancel"
                    class="rounded-lg bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-600 active:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:active:bg-slate-600"
                >
                    Cancel
                </button>
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
                        <button
                            type="button"
                            wire:click="discover({{ $machine->id }})"
                            wire:loading.attr="disabled"
                            wire:target="discover({{ $machine->id }})"
                            class="rounded-lg bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-600 active:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-400"
                        >
                            Scan
                        </button>
                        <button
                            type="button"
                            wire:click="edit({{ $machine->id }})"
                            aria-label="Edit {{ $machine->name }}"
                            class="flex h-10 w-10 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                        >
                            <x-icons.pencil class="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            wire:click="delete({{ $machine->id }})"
                            wire:confirm="Delete this scan target?"
                            aria-label="Delete {{ $machine->name }}"
                            class="flex h-10 w-10 items-center justify-center rounded-full text-slate-400 hover:bg-rose-50 hover:text-rose-500 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                        >
                            <x-icons.trash class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                @if ($scanningMachineId === $machine->id)
                    <div class="mt-3 space-y-2 border-t border-slate-100 pt-3 dark:border-slate-700" wire:loading.remove wire:target="discover({{ $machine->id }})">
                        @if ($scanError)
                            <p class="text-sm text-amber-600 dark:text-amber-400">{{ $scanError }}</p>
                        @endif

                        @foreach ($discovered as $result)
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <span class="truncate text-slate-600 dark:text-slate-300">
                                    {{ $result['name'] }} <span class="text-slate-400 dark:text-slate-500">:{{ $result['port'] }}</span>
                                </span>
                                <button
                                    type="button"
                                    wire:click="addCardFromDiscovery('{{ $result['name'] }}', '{{ $result['url'] }}')"
                                    class="shrink-0 rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-600 active:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:active:bg-slate-600"
                                >
                                    Add card
                                </button>
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
