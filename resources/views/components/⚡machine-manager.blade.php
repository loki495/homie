<?php

use App\Enums\DiscoveryMethod;
use App\Models\Machine;
use App\Support\Discovery\MachineDiscovery;
use Illuminate\Database\Eloquent\Collection;
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

    public bool $hasSshPrivateKey = false;

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

        $attributes = [
            'name' => $this->name,
            'host' => $this->host,
            'description' => $this->description !== '' ? $this->description : null,
            'discovery_method' => DiscoveryMethod::from($this->discovery_method),
            'ssh_user' => $this->discovery_method === 'ssh' && $this->ssh_user !== '' ? $this->ssh_user : null,
            'ssh_port' => $this->discovery_method === 'ssh' && $this->ssh_port !== '' ? (int) $this->ssh_port : null,
        ];

        if ($this->discovery_method !== 'ssh') {
            $attributes['ssh_private_key'] = null;
        } elseif ($this->ssh_private_key !== '') {
            $attributes['ssh_private_key'] = $this->ssh_private_key;
        }
        // else: field left blank on an existing key — leave ssh_private_key untouched.

        $machine->fill($attributes)->save();

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
        $this->ssh_private_key = '';
        $this->hasSshPrivateKey = $machine->ssh_private_key !== null;
        $this->dispatch('scroll-sidebar-top');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'host', 'description', 'ssh_user', 'ssh_port', 'ssh_private_key', 'hasSshPrivateKey']);
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

        if (config('homie.demo_mode')) {
            $this->discovered = $this->demoDiscoveryContainers();
            $this->scanError = null;

            return;
        }

        $result = match ($machine->discovery_method) {
            DiscoveryMethod::Docker => app(MachineDiscovery::class)->viaDocker($machine),
            DiscoveryMethod::Ssh => app(MachineDiscovery::class)->viaSsh($machine),
        };

        $this->discovered = $result->containers;
        $this->scanError = $result->error;
    }

    /**
     * Demo mode only: there is nothing real to scan (mounting a real Docker
     * socket into a public demo container is off the table - see this repo's
     * CLAUDE.md "Design principle" and the outside-repo demo-sites-and-cd
     * plan), so a canned result stands in for a real scan. Mapped to the mock
     * arr-stack services in docker-compose.yml so the Discovery UI stays fully
     * clickable and "Add to cards" leads somewhere real, not a dead link.
     *
     * @return list<array{name: string, image: string, url: string}>
     */
    private function demoDiscoveryContainers(): array
    {
        return [
            ['name' => 'sonarr', 'image' => 'linuxserver/sonarr', 'url' => (string) config('homie.demo_mock_sonarr_url')],
            ['name' => 'radarr', 'image' => 'linuxserver/radarr', 'url' => (string) config('homie.demo_mock_radarr_url')],
        ];
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
                placeholder="{{ $hasSshPrivateKey ? 'Key on file — paste a new one to replace it' : '-----BEGIN OPENSSH PRIVATE KEY-----'."\n...\n".'-----END OPENSSH PRIVATE KEY-----' }}"
                class="font-mono"
            />
            <p class="text-sm text-slate-400 dark:text-slate-500">
                Runs <code>docker ps</code> over SSH. Key-only auth (no passwords) — paste a private key with no
                passphrase, dedicated to this purpose. Stored encrypted, never shown again once saved.
                @if ($hasSshPrivateKey)
                    Leave blank to keep the key already on file.
                @else
                    Leave blank to rely on an agent already available to the container.
                @endif
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
                        <x-manage-item-actions
                            edit-action="edit({{ $machine->id }})"
                            delete-action="delete({{ $machine->id }})"
                            label="{{ $machine->name }}"
                            confirm="Delete this scan target?"
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
