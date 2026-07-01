<?php

use App\Enums\CardType;
use App\Models\Card;
use App\Models\Machine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

new class extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $host = '';

    public string $description = '';

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
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'host', 'description']);
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

<div class="space-y-4">
    <form wire:submit="save" class="space-y-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">
            {{ $editingId ? 'Edit scan target' : 'New scan target' }}
        </label>

        <input
            type="text"
            wire:model="name"
            placeholder="Name, e.g. NAS"
            class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
        >
        @error('name')
            <p class="text-xs text-rose-500">{{ $message }}</p>
        @enderror

        <input
            type="text"
            wire:model="host"
            placeholder="192.168.1.50 or nas.lan"
            class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
        >
        @error('host')
            <p class="text-xs text-rose-500">{{ $message }}</p>
        @enderror

        <input
            type="text"
            wire:model="description"
            placeholder="Notes (optional)"
            class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
        >

        <p class="text-xs text-slate-400 dark:text-slate-500">
            Discovery assumes the Docker Engine API is reachable at <code>http://host:2375</code>. Enter a full
            URL here (e.g. <code>http://host:2376</code>) if yours differs.
        </p>

        <div class="flex gap-2">
            <button
                type="submit"
                class="rounded-md bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white dark:bg-slate-100 dark:text-slate-800"
            >
                {{ $editingId ? 'Save' : 'Add' }}
            </button>
            @if ($editingId)
                <button
                    type="button"
                    wire:click="cancel"
                    class="rounded-md bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200"
                >
                    Cancel
                </button>
            @endif
        </div>
    </form>

    <ul class="space-y-2">
        @forelse ($this->machines() as $machine)
            <li class="rounded-md border border-slate-200 px-3 py-2 dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <div class="min-w-0">
                        <p class="truncate text-sm text-slate-700 dark:text-slate-200">{{ $machine->name }}</p>
                        <p class="truncate text-xs text-slate-400 dark:text-slate-500">{{ $machine->host }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2 text-xs">
                        <button
                            type="button"
                            wire:click="discover({{ $machine->id }})"
                            wire:loading.attr="disabled"
                            wire:target="discover({{ $machine->id }})"
                            class="text-indigo-500 hover:text-indigo-600"
                        >
                            Discover
                        </button>
                        <button type="button" wire:click="edit({{ $machine->id }})" class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                            Edit
                        </button>
                        <button
                            type="button"
                            wire:click="delete({{ $machine->id }})"
                            wire:confirm="Delete this scan target?"
                            class="text-rose-500 hover:text-rose-600"
                        >
                            Delete
                        </button>
                    </div>
                </div>

                @if ($scanningMachineId === $machine->id)
                    <div class="mt-2 space-y-1 border-t border-slate-100 pt-2 dark:border-slate-700" wire:loading.remove wire:target="discover({{ $machine->id }})">
                        @if ($scanError)
                            <p class="text-xs text-amber-600 dark:text-amber-400">{{ $scanError }}</p>
                        @endif

                        @foreach ($discovered as $result)
                            <div class="flex items-center justify-between text-xs">
                                <span class="truncate text-slate-600 dark:text-slate-300">
                                    {{ $result['name'] }} <span class="text-slate-400 dark:text-slate-500">:{{ $result['port'] }}</span>
                                </span>
                                <button
                                    type="button"
                                    wire:click="addCardFromDiscovery('{{ $result['name'] }}', '{{ $result['url'] }}')"
                                    class="shrink-0 rounded bg-slate-100 px-2 py-0.5 font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200"
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
