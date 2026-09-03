<?php

use App\Models\Card;
use Livewire\Component;

new class extends Component
{
    public Card $card;

    public ?string $status = null;

    public ?string $summary = null;

    /** @var list<array{label: string, value: string}> */
    public array $stats = [];

    /** @var list<array{name: string, at: string}> */
    public array $downloaded = [];

    /** @var list<array{name: string, at: string}> */
    public array $deleted = [];

    public ?string $current = null;

    public function mount(): void
    {
        $api = $this->card->api()->first();

        if (! $api) {
            return;
        }

        $result = $api->provider->fetcher()->fetch($api);

        $this->status = $result['status'];
        $this->summary = $result['summary'];
        $this->stats = $result['stats'];
        $this->downloaded = $result['downloaded'] ?? [];
        $this->deleted = $result['deleted'] ?? [];
        $this->current = $result['current'] ?? null;

        $api->update([
            'cached_data' => $result['status'] === 'ok' ? $result['raw'] : null,
            'last_fetched_at' => now(),
        ]);
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="animate-pulse">
                <div class="ml-auto h-3 w-3 rounded-full bg-slate-100 dark:bg-slate-700/50"></div>
                <div class="mt-2 h-3 w-32 rounded bg-slate-100 dark:bg-slate-700/50"></div>
            </div>
        HTML;
    }
};
?>

<div>
    <div class="flex justify-end">
        <span @class([
            'h-2 w-2 rounded-full',
            'bg-emerald-500' => $status === 'ok',
            'bg-rose-500' => $status === 'error',
            'bg-slate-300' => $status === null,
        ])></span>
    </div>
    @if (count($stats))
        <div class="mt-1 flex flex-wrap gap-1.5">
            @foreach ($stats as $stat)
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500 dark:bg-slate-700 dark:text-slate-400">
                    {{ $stat['label'] }}: <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $stat['value'] }}</span>
                </span>
            @endforeach
        </div>
    @else
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $summary }}</p>
    @endif

    @if (count($downloaded) || count($deleted))
        <div class="mt-2 space-y-1">
            @if (count($downloaded))
                <details class="group">
                    <summary
                        onclick="event.stopPropagation()"
                        class="cursor-pointer text-xs font-medium text-slate-500 select-none dark:text-slate-400"
                    >
                        Recently downloaded
                    </summary>
                    <ul class="mt-1 space-y-0.5 border-l border-slate-200 pl-2 dark:border-slate-700">
                        @foreach ($downloaded as $item)
                            <li class="flex items-baseline justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
                                <span class="min-w-0 truncate" title="{{ $item['name'] }}">{{ $item['name'] }}</span>
                                <span class="shrink-0 text-slate-400 dark:text-slate-500">{{ $item['at'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
            @if (count($deleted))
                <details class="group">
                    <summary
                        onclick="event.stopPropagation()"
                        class="cursor-pointer text-xs font-medium text-slate-500 select-none dark:text-slate-400"
                    >
                        Recently deleted
                    </summary>
                    <ul class="mt-1 space-y-0.5 border-l border-slate-200 pl-2 dark:border-slate-700">
                        @foreach ($deleted as $item)
                            <li class="flex items-baseline justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
                                <span class="min-w-0 truncate" title="{{ $item['name'] }}">{{ $item['name'] }}</span>
                                <span class="shrink-0 text-slate-400 dark:text-slate-500">{{ $item['at'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif

    @if ($current)
        <p class="mt-1 truncate text-xs text-slate-400 dark:text-slate-500" title="{{ $current }}">{{ $current }}</p>
    @endif
</div>
