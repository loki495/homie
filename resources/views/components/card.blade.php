@props(['card', 'editing' => false])

<div class="relative">
    @if ($editing)
        <div class="absolute -top-2 -right-2 z-10 flex gap-1 rounded-full border border-slate-200 bg-white p-1 shadow dark:border-slate-600 dark:bg-slate-700">
            <button
                type="button"
                wire:click="moveCard({{ $card->id }}, -1)"
                class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:text-slate-400 dark:hover:bg-slate-600 dark:hover:text-slate-200"
            >
                ↑
            </button>
            <button
                type="button"
                wire:click="moveCard({{ $card->id }}, 1)"
                class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:text-slate-400 dark:hover:bg-slate-600 dark:hover:text-slate-200"
            >
                ↓
            </button>
        </div>
    @endif

    @if ($card->type === \App\Enums\CardType::Link)
        @if ($editing)
            <div class="block rounded-lg border border-slate-200 bg-white p-5 sm:p-4 opacity-75 dark:border-slate-700 dark:bg-slate-800">
                <div class="flex items-center gap-2.5">
                    @if ($card->icon)
                        <x-card-icon :src="$card->icon" class="h-8 w-8 shrink-0 sm:h-6 sm:w-6 object-contain" />
                    @endif
                    <h3 class="truncate text-base font-semibold sm:text-sm text-slate-700 dark:text-slate-200">{{ $card->name }}</h3>
                </div>
                <p class="mt-1 truncate text-xs text-slate-400 dark:text-slate-500">{{ $card->url }}</p>
            </div>
        @else
            <a
                href="{{ $card->url }}"
                target="_blank"
                rel="noopener"
                class="block rounded-lg border border-slate-200 bg-white p-5 sm:p-4 shadow-sm transition hover:shadow-md dark:border-slate-700 dark:bg-slate-800 dark:hover:shadow-slate-900/50"
            >
                <div class="flex items-center gap-2.5">
                    @if ($card->icon)
                        <x-card-icon :src="$card->icon" class="h-8 w-8 shrink-0 sm:h-6 sm:w-6 object-contain" />
                    @endif
                    <h3 class="truncate text-base font-semibold sm:text-sm text-slate-700 dark:text-slate-200">{{ $card->name }}</h3>
                </div>
                <p class="mt-1 truncate text-xs text-slate-400 dark:text-slate-500">{{ $card->url }}</p>
            </a>
        @endif
    @elseif ($card->type === \App\Enums\CardType::Output)
        <div @class(['rounded-lg border border-slate-200 bg-white p-5 sm:p-4 dark:border-slate-700 dark:bg-slate-800', 'opacity-75' => $editing])>
            <div class="flex items-center gap-2.5">
                @if ($card->icon)
                    <x-card-icon :src="$card->icon" class="h-7 w-7 shrink-0 sm:h-5 sm:w-5 object-contain" />
                @endif
                <h3 class="truncate text-base font-semibold sm:text-sm text-slate-700 dark:text-slate-200">{{ $card->name }}</h3>
            </div>
            <livewire:card-output-widget :card="$card" :key="'card-output-'.$card->id" lazy />
        </div>
    @elseif ($card->type === \App\Enums\CardType::Trigger)
        <div @class(['rounded-lg border border-slate-200 bg-white p-5 sm:p-4 dark:border-slate-700 dark:bg-slate-800', 'opacity-75' => $editing])>
            <div class="flex items-center gap-2.5">
                @if ($card->icon)
                    <x-card-icon :src="$card->icon" class="h-7 w-7 shrink-0 sm:h-5 sm:w-5 object-contain" />
                @endif
                <h3 class="truncate text-base font-semibold sm:text-sm text-slate-700 dark:text-slate-200">{{ $card->name }}</h3>
            </div>
            @if ($editing)
                <p class="mt-2 truncate font-mono text-xs text-slate-400 dark:text-slate-500">{{ $card->output?->command }}</p>
            @else
                <livewire:card-trigger-widget :card="$card" :key="'card-trigger-'.$card->id" />
            @endif
        </div>
    @elseif ($card->type === \App\Enums\CardType::Api)
        @php
            $apiCardClasses = 'block rounded-lg border border-slate-200 bg-white p-5 sm:p-4 dark:border-slate-700 dark:bg-slate-800'.($editing ? ' opacity-75' : ' cursor-pointer');
        @endphp
        {{-- Tapping the card expands it (see the wrapper in dashboard.blade.php, which owns the state and
             the full-row span) so a phone-sized card can show its untruncated text; the arrow keeps the
             one-tap path to the service. --}}
        <div
            class="{{ $apiCardClasses }}"
            @unless ($editing)
                x-on:click="expanded = ! expanded"
                x-bind:class="expanded && 'ring-2 ring-teal-600/40 dark:ring-teal-500/40'"
                x-bind:data-expanded="expanded ? 'true' : null"
            @endunless
        >
            <div class="flex items-center gap-2.5">
                @if ($card->icon)
                    <x-card-icon :src="$card->icon" class="h-7 w-7 shrink-0 sm:h-5 sm:w-5 object-contain" />
                @endif
                <h3 class="min-w-0 flex-1 truncate text-base font-semibold sm:text-sm text-slate-700 dark:text-slate-200">{{ $card->name }}</h3>
            </div>
            <livewire:card-api-widget :card="$card" :linked="! $editing" :key="'card-api-'.$card->id" lazy />
        </div>
    @endif
</div>
