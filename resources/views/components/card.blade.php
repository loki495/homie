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
            <div class="block rounded-lg border border-slate-200 bg-white p-4 opacity-75 dark:border-slate-700 dark:bg-slate-800">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $card->name }}</h3>
                <p class="mt-1 truncate text-xs text-slate-400 dark:text-slate-500">{{ $card->url }}</p>
            </div>
        @else
            <a
                href="{{ $card->url }}"
                target="_blank"
                rel="noopener"
                class="block rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-slate-700 dark:bg-slate-800 dark:hover:shadow-slate-900/50"
            >
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $card->name }}</h3>
                <p class="mt-1 truncate text-xs text-slate-400 dark:text-slate-500">{{ $card->url }}</p>
            </a>
        @endif
    @elseif ($card->type === \App\Enums\CardType::Output)
        <livewire:card-output-widget :card="$card" :key="'card-output-'.$card->id" lazy />
    @elseif ($card->type === \App\Enums\CardType::Api)
        <livewire:card-api-widget :card="$card" :key="'card-api-'.$card->id" lazy />
    @endif
</div>
