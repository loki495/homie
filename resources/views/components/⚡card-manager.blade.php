<?php

use App\Enums\ApiProvider;
use App\Enums\CardType;
use App\Models\Card;
use App\Models\Group;
use App\Support\DashboardIcons;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
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

    public string $auth_type = 'api_key';

    public string $api_key = '';

    public string $username = '';

    public string $password = '';

    public string $icon = '';

    public string $iconQuery = '';

    /** @var list<array{name: string, url: string}> */
    public array $iconResults = [];

    #[On('prefill-card')]
    public function prefillFromDiscovery(string $name, string $url): void
    {
        $this->resetForm();
        $this->name = $name;
        $this->type = 'link';
        $this->url = $url;
        $this->iconQuery = $name;
        $this->iconResults = app(DashboardIcons::class)->search($name);
        $this->dispatch('scroll-sidebar-top');
    }

    public function updatedIconQuery(): void
    {
        $this->iconResults = app(DashboardIcons::class)->search($this->iconQuery);
    }

    public function updatedType(string $value): void
    {
        if ($value === 'api' && $this->base_url === '' && $this->url !== '') {
            $this->base_url = $this->url;
        } elseif ($value === 'link' && $this->url === '' && $this->base_url !== '') {
            $this->url = $this->base_url;
        }
    }

    public function selectIcon(string $url): void
    {
        $this->icon = $url;
        $this->iconQuery = '';
        $this->iconResults = [];
    }

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
            'icon' => $this->icon !== '' ? $this->icon : null,
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
                'auth_type' => $this->auth_type,
                'api_key' => $this->auth_type === 'api_key' && $this->api_key !== '' ? $this->api_key : null,
                'username' => $this->auth_type === 'basic' && $this->username !== '' ? $this->username : null,
                'password' => $this->auth_type === 'basic' && $this->password !== '' ? $this->password : null,
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
        $this->auth_type = $card->api?->auth_type ?? 'api_key';
        $this->api_key = $card->api?->api_key ?? '';
        $this->username = $card->api?->username ?? '';
        $this->password = $card->api?->password ?? '';
        $this->icon = (string) $card->icon;
        $this->dispatch('scroll-sidebar-top');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'group_id', 'url', 'command', 'base_url', 'api_key', 'username', 'password', 'icon', 'iconQuery', 'iconResults']);
        $this->type = 'link';
        $this->provider = 'generic';
        $this->auth_type = 'api_key';
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
        <flux:heading size="sm">{{ $editingId ? 'Edit card' : 'New card' }}</flux:heading>

        <flux:input wire:model="name" placeholder="Name" />

        <div class="space-y-2">
            <div class="flex items-center gap-2">
                @if ($icon)
                    <img src="{{ $icon }}" alt="" class="h-9 w-9 shrink-0 rounded-md border border-slate-200 object-contain p-1 dark:border-slate-600">
                @else
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-dashed border-slate-300 dark:border-slate-600"></div>
                @endif
                <flux:input wire:model="icon" placeholder="Icon URL (optional)" class="flex-1" />
                @if ($icon)
                    <flux:button icon="x-mark" variant="ghost" size="sm" wire:click="$set('icon', '')" aria-label="Clear icon" />
                @endif
            </div>
            <flux:input wire:model.live.debounce.400ms="iconQuery" placeholder="Search icons, e.g. sonarr" />
            @if (count($iconResults))
                <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                    @foreach ($iconResults as $result)
                        <button
                            type="button"
                            wire:click="selectIcon('{{ $result['url'] }}')"
                            class="flex flex-col items-center gap-1 rounded-md border border-slate-200 p-2 hover:border-slate-400 dark:border-slate-600 dark:hover:border-slate-400"
                        >
                            <img src="{{ $result['url'] }}" alt="" class="h-6 w-6 object-contain">
                            <span class="w-full truncate text-center text-xs text-slate-500 dark:text-slate-400">{{ $result['name'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <flux:select wire:model.live="type">
                @foreach ($this->cardTypes() as $cardType)
                    <flux:select.option value="{{ $cardType->value }}">{{ ucfirst($cardType->value) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="group_id">
                <flux:select.option value="">No group</flux:select.option>
                @foreach ($this->groupOptions() as $group)
                    <flux:select.option value="{{ $group->id }}">{{ $group->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($type === 'link')
            <flux:input wire:model="url" placeholder="https://example.lan" />
        @elseif ($type === 'output')
            <flux:textarea wire:model="command" rows="2" placeholder="df -h /" class="font-mono" />
        @elseif ($type === 'api')
            <flux:select wire:model="provider">
                @foreach ($this->apiProviders() as $apiProvider)
                    <flux:select.option value="{{ $apiProvider->value }}">{{ $apiProvider->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="base_url" placeholder="http://nas.lan:8989" />
            <flux:select wire:model.live="auth_type">
                <flux:select.option value="api_key">API key</flux:select.option>
                <flux:select.option value="basic">Username &amp; password</flux:select.option>
            </flux:select>
            @if ($auth_type === 'basic')
                <flux:input wire:model="username" placeholder="Username" />
                <flux:input wire:model="password" type="password" placeholder="Password" />
            @else
                <flux:input wire:model="api_key" placeholder="API key (optional)" />
            @endif
        @endif

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary" class="flex-1">
                {{ $editingId ? 'Save' : 'Add card' }}
            </flux:button>
            @if ($editingId)
                <flux:button type="button" wire:click="cancel">Cancel</flux:button>
            @endif
        </div>
    </form>

    <ul class="space-y-2">
        @forelse ($this->cards() as $card)
            <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 p-3.5 dark:border-slate-700">
                <div class="flex min-w-0 items-center gap-2.5">
                    @if ($card->icon)
                        <img src="{{ $card->icon }}" alt="" class="h-8 w-8 shrink-0 rounded-md object-contain">
                    @endif
                    <div class="min-w-0">
                        <p class="truncate text-sm text-slate-700 dark:text-slate-200">{{ $card->name }}</p>
                        <p class="text-sm text-slate-400 dark:text-slate-500">
                            {{ ucfirst($card->type->value) }} &middot; {{ $card->group?->name ?? 'Ungrouped' }}
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <flux:button
                        icon="pencil"
                        variant="ghost"
                        size="sm"
                        wire:click="edit({{ $card->id }})"
                        aria-label="Edit {{ $card->name }}"
                    />
                    <flux:button
                        icon="trash"
                        variant="ghost"
                        size="sm"
                        class="!text-rose-500 hover:!text-rose-600 dark:!text-rose-400 dark:hover:!text-rose-300"
                        wire:click="delete({{ $card->id }})"
                        wire:confirm="Delete this card?"
                        aria-label="Delete {{ $card->name }}"
                    />
                </div>
            </li>
        @empty
            <li class="text-sm text-slate-400 dark:text-slate-500">No cards yet.</li>
        @endforelse
    </ul>
</div>
