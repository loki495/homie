<?php

use App\Models\Card;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Card $card;

    public ?string $status = null;

    public ?string $summary = null;

    /** @var list<array{label: string, value: string}> */
    public array $stats = [];

    #[On('dashboard-updated')]
    public function refreshCard(): void
    {
        $this->card = $this->card->fresh();
    }

    public function mount(): void
    {
        $api = $this->card->api()->first();

        if (! $api) {
            return;
        }

        $fetcher = $api->provider->fetcher();

        if (! $fetcher) {
            $this->status = 'unsupported';
            $this->summary = $api->provider->label().' integration not implemented yet.';

            return;
        }

        $result = $fetcher->fetch($api);

        $this->status = $result['status'];
        $this->summary = $result['summary'];
        $this->stats = $result['stats'];

        $api->update([
            'cached_data' => $result['status'] === 'ok' ? $result['raw'] : null,
            'last_fetched_at' => now(),
        ]);
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="animate-pulse rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                <div class="h-4 w-24 rounded bg-slate-200 dark:bg-slate-700"></div>
                <div class="mt-3 h-3 w-32 rounded bg-slate-100 dark:bg-slate-700/50"></div>
            </div>
        HTML;
    }
};
?>

<div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
    <div class="flex items-center justify-between">
        <div class="flex min-w-0 items-center gap-2.5">
            @if ($card->icon)
                <img src="{{ $card->icon }}" alt="" class="h-5 w-5 shrink-0 object-contain">
            @endif
            <h3 class="truncate text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $card->name }}</h3>
        </div>
        <span @class([
            'h-2 w-2 rounded-full',
            'bg-emerald-500' => $status === 'ok',
            'bg-rose-500' => $status === 'error',
            'bg-amber-400' => $status === 'unsupported',
            'bg-slate-300' => $status === null,
        ])></span>
    </div>
    @if (count($stats))
        <div class="mt-2 flex flex-wrap gap-1.5">
            @foreach ($stats as $stat)
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500 dark:bg-slate-700 dark:text-slate-400">
                    {{ $stat['label'] }}: <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $stat['value'] }}</span>
                </span>
            @endforeach
        </div>
    @else
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $summary }}</p>
    @endif
</div>
