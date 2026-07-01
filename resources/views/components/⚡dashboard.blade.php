<?php

use App\Models\Card;
use App\Models\Group;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $editing = false;

    public function toggleEditing(): void
    {
        $this->editing = ! $this->editing;
    }

    #[On('dashboard-updated')]
    public function refresh(): void
    {
        // Re-renders with fresh groups()/ungroupedCards() queries — no state to update.
    }

    public function moveCard(int $cardId, int $direction): void
    {
        $card = Card::findOrFail($cardId);

        $sibling = Card::query()
            ->where('group_id', $card->group_id)
            ->when(
                $direction < 0,
                fn ($query) => $query->where('sort_order', '<', $card->sort_order)->orderByDesc('sort_order'),
                fn ($query) => $query->where('sort_order', '>', $card->sort_order)->orderBy('sort_order'),
            )
            ->first();

        if (! $sibling) {
            return;
        }

        [$card->sort_order, $sibling->sort_order] = [$sibling->sort_order, $card->sort_order];

        $card->save();
        $sibling->save();
    }

    public function moveGroup(int $groupId, int $direction): void
    {
        $group = Group::findOrFail($groupId);

        $sibling = Group::query()
            ->when(
                $direction < 0,
                fn ($query) => $query->where('sort_order', '<', $group->sort_order)->orderByDesc('sort_order'),
                fn ($query) => $query->where('sort_order', '>', $group->sort_order)->orderBy('sort_order'),
            )
            ->first();

        if (! $sibling) {
            return;
        }

        [$group->sort_order, $sibling->sort_order] = [$sibling->sort_order, $group->sort_order];

        $group->save();
        $sibling->save();
    }

    /**
     * @return Collection<int, Group>
     */
    public function groups(): Collection
    {
        return Group::query()
            ->with(['cards' => fn ($query) => $query->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return Collection<int, Card>
     */
    public function ungroupedCards(): Collection
    {
        return Card::query()
            ->whereNull('group_id')
            ->orderBy('sort_order')
            ->get();
    }
};
?>

<div class="min-h-screen bg-slate-100 pb-10 dark:bg-slate-900">
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-4 sm:px-6 dark:border-slate-800 dark:bg-slate-800">
        <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Homie</h1>
        <div class="flex items-center gap-2">
            <button
                type="button"
                x-data
                x-on:click="$store.theme.toggle()"
                class="rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200"
            >
                <span x-show="!$store.theme.dark">🌙</span>
                <span x-show="$store.theme.dark" x-cloak>☀️</span>
            </button>
            <button
                type="button"
                wire:click="toggleEditing"
                @class([
                    'rounded-md px-3 py-1.5 text-xs font-semibold',
                    'bg-slate-800 text-white dark:bg-slate-100 dark:text-slate-800' => $editing,
                    'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200' => ! $editing,
                ])
            >
                {{ $editing ? 'Done' : 'Arrange' }}
            </button>
            <button
                type="button"
                x-data
                x-on:click="$store.sidebar.open = true"
                class="rounded-md bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200"
            >
                Manage
            </button>
        </div>
    </header>

    <main class="mx-auto max-w-5xl space-y-6 p-4 sm:p-6">
        @foreach ($this->groups() as $group)
            <section
                x-data="{ open: {{ $group->collapsed ? 'false' : 'true' }} }"
                class="rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-center justify-between px-4 py-3">
                    <button type="button" @click="open = ! open" class="flex flex-1 items-center gap-2 text-left">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $group->name }}</span>
                        <svg
                            x-bind:class="open ? 'rotate-180' : ''"
                            class="h-4 w-4 shrink-0 text-slate-400 transition-transform dark:text-slate-500"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    @if ($editing)
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                wire:click="moveGroup({{ $group->id }}, -1)"
                                class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:text-slate-500 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                            >
                                ↑
                            </button>
                            <button
                                type="button"
                                wire:click="moveGroup({{ $group->id }}, 1)"
                                class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:text-slate-500 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                            >
                                ↓
                            </button>
                        </div>
                    @endif
                </div>
                <div
                    x-show="open"
                    x-transition
                    class="grid grid-cols-1 gap-3 border-t border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-3 dark:border-slate-700"
                >
                    @foreach ($group->cards as $card)
                        <x-card :card="$card" :editing="$editing" />
                    @endforeach
                </div>
            </section>
        @endforeach

        @if ($this->ungroupedCards()->isNotEmpty())
            <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->ungroupedCards() as $card)
                    <x-card :card="$card" :editing="$editing" />
                @endforeach
            </section>
        @endif
    </main>
</div>
