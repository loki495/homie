<?php

use App\Enums\ApiProvider;
use App\Enums\CardType;
use App\Models\Card;
use App\Models\Group;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

new class extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'link';

    public ?int $group_id = null;

    public string $url = '';

    public string $command = '';

    public string $provider = 'generic';

    public string $base_url = '';

    public string $api_key = '';

    /**
     * @return array<string, string>
     */
    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:link,output,api',
            'group_id' => 'nullable|exists:groups,id',
            ...match ($this->type) {
                'link' => ['url' => 'required|url'],
                'output' => ['command' => 'required|string'],
                'api' => ['base_url' => 'required|url', 'provider' => 'required|string'],
                default => [],
            },
        ];
    }

    public function save(): void
    {
        $this->validate();

        $card = $this->editingId ? Card::findOrFail($this->editingId) : new Card;

        $card->fill([
            'name' => $this->name,
            'type' => CardType::from($this->type),
            'group_id' => $this->group_id,
            'url' => match ($this->type) {
                'link' => $this->url,
                'api' => $this->base_url,
                default => null,
            },
        ]);

        if (! $card->exists) {
            $card->sort_order = (Card::where('group_id', $this->group_id)->max('sort_order') ?? -1) + 1;
        }

        $card->save();

        if ($this->type === 'output') {
            $card->output()->updateOrCreate([], ['command' => $this->command]);
        } else {
            $card->output()->delete();
        }

        if ($this->type === 'api') {
            $card->api()->updateOrCreate([], [
                'provider' => ApiProvider::from($this->provider),
                'base_url' => $this->base_url,
                'api_key' => $this->api_key !== '' ? $this->api_key : null,
            ]);
        } else {
            $card->api()->delete();
        }

        $this->resetForm();
        $this->dispatch('dashboard-updated');
    }

    public function edit(int $cardId): void
    {
        $card = Card::with(['output', 'api'])->findOrFail($cardId);

        $this->editingId = $card->id;
        $this->name = $card->name;
        $this->type = $card->type->value;
        $this->group_id = $card->group_id;
        $this->url = $card->type === CardType::Link ? (string) $card->url : '';
        $this->command = $card->output?->command ?? '';
        $this->provider = $card->api?->provider?->value ?? 'generic';
        $this->base_url = $card->api?->base_url ?? '';
        $this->api_key = $card->api?->api_key ?? '';
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'group_id', 'url', 'command', 'base_url', 'api_key']);
        $this->type = 'link';
        $this->provider = 'generic';
        $this->resetValidation();
    }

    public function delete(int $cardId): void
    {
        Card::findOrFail($cardId)->delete();

        if ($this->editingId === $cardId) {
            $this->resetForm();
        }

        $this->dispatch('dashboard-updated');
    }

    /**
     * @return Collection<int, Card>
     */
    public function cards(): Collection
    {
        return Card::query()->with('group')->orderBy('group_id')->orderBy('sort_order')->get();
    }

    /**
     * @return Collection<int, Group>
     */
    public function groupOptions(): Collection
    {
        return Group::query()->orderBy('sort_order')->get();
    }

    /**
     * @return list<CardType>
     */
    public function cardTypes(): array
    {
        return CardType::cases();
    }

    /**
     * @return list<ApiProvider>
     */
    public function apiProviders(): array
    {
        return ApiProvider::cases();
    }
};
?>

<div class="space-y-6">
    <form wire:submit="save" class="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
        <label class="block text-sm font-medium text-slate-500 dark:text-slate-400">
            {{ $editingId ? 'Edit card' : 'New card' }}
        </label>

        <input
            type="text"
            wire:model="name"
            placeholder="Name"
            class="w-full rounded-lg border-slate-300 px-3.5 py-3 text-base sm:text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400"
        >
        @error('name')
            <p class="text-sm text-rose-500">{{ $message }}</p>
        @enderror

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <select
                wire:model.live="type"
                class="w-full rounded-lg border-slate-300 px-3.5 py-3 text-base sm:text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
            >
                @foreach ($this->cardTypes() as $cardType)
                    <option value="{{ $cardType->value }}">{{ ucfirst($cardType->value) }}</option>
                @endforeach
            </select>

            <select
                wire:model="group_id"
                class="w-full rounded-lg border-slate-300 px-3.5 py-3 text-base sm:text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
            >
                <option value="">No group</option>
                @foreach ($this->groupOptions() as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>
        </div>

        @if ($type === 'link')
            <input
                type="text"
                wire:model="url"
                placeholder="https://example.lan"
                class="w-full rounded-lg border-slate-300 px-3.5 py-3 text-base sm:text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400"
            >
            @error('url')
                <p class="text-sm text-rose-500">{{ $message }}</p>
            @enderror
        @elseif ($type === 'output')
            <textarea
                wire:model="command"
                rows="2"
                placeholder="df -h /"
                class="w-full rounded-lg border-slate-300 px-3.5 py-3 font-mono text-base sm:text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400"
            ></textarea>
            @error('command')
                <p class="text-sm text-rose-500">{{ $message }}</p>
            @enderror
        @elseif ($type === 'api')
            <select
                wire:model="provider"
                class="w-full rounded-lg border-slate-300 px-3.5 py-3 text-base sm:text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
            >
                @foreach ($this->apiProviders() as $apiProvider)
                    <option value="{{ $apiProvider->value }}">{{ $apiProvider->label() }}</option>
                @endforeach
            </select>
            <input
                type="text"
                wire:model="base_url"
                placeholder="http://nas.lan:8989"
                class="w-full rounded-lg border-slate-300 px-3.5 py-3 text-base sm:text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400"
            >
            @error('base_url')
                <p class="text-sm text-rose-500">{{ $message }}</p>
            @enderror
            <input
                type="text"
                wire:model="api_key"
                placeholder="API key (optional)"
                class="w-full rounded-lg border-slate-300 px-3.5 py-3 text-base sm:text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400"
            >
        @endif

        <div class="flex gap-2">
            <button
                type="submit"
                class="flex-1 rounded-lg bg-slate-800 px-4 py-3 text-sm font-semibold text-white active:bg-slate-700 dark:bg-slate-100 dark:text-slate-800 dark:active:bg-slate-200"
            >
                {{ $editingId ? 'Save' : 'Add card' }}
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
        @forelse ($this->cards() as $card)
            <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 p-3.5 dark:border-slate-700">
                <div class="min-w-0">
                    <p class="truncate text-sm text-slate-700 dark:text-slate-200">{{ $card->name }}</p>
                    <p class="text-sm text-slate-400 dark:text-slate-500">
                        {{ ucfirst($card->type->value) }} &middot; {{ $card->group?->name ?? 'Ungrouped' }}
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <button
                        type="button"
                        wire:click="edit({{ $card->id }})"
                        aria-label="Edit {{ $card->name }}"
                        class="flex h-10 w-10 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                    >
                        <x-icons.pencil class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        wire:click="delete({{ $card->id }})"
                        wire:confirm="Delete this card?"
                        aria-label="Delete {{ $card->name }}"
                        class="flex h-10 w-10 items-center justify-center rounded-full text-slate-400 hover:bg-rose-50 hover:text-rose-500 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                    >
                        <x-icons.trash class="h-5 w-5" />
                    </button>
                </div>
            </li>
        @empty
            <li class="text-sm text-slate-400 dark:text-slate-500">No cards yet.</li>
        @endforelse
    </ul>
</div>
