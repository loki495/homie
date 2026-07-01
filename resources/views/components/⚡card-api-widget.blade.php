<?php

use App\Enums\ApiProvider;
use App\Models\Card;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

new class extends Component
{
    public Card $card;

    public ?string $status = null;

    public ?string $summary = null;

    public function mount(Card $card): void
    {
        $this->card = $card;

        $api = $card->api;

        if (! $api) {
            return;
        }

        if ($api->provider !== ApiProvider::Generic) {
            $this->status = 'unsupported';
            $this->summary = $api->provider->label().' integration not implemented yet.';

            return;
        }

        try {
            $response = Http::timeout(5)
                ->when(
                    $api->auth_type === 'basic' && $api->username,
                    fn ($http) => $http->withBasicAuth($api->username, $api->password ?? '')
                )
                ->when(
                    $api->auth_type !== 'basic' && $api->api_key,
                    fn ($http) => $http->withHeader('X-Api-Key', $api->api_key)
                )
                ->get($api->base_url);

            $this->status = $response->successful() ? 'ok' : 'error';
            $this->summary = $response->successful()
                ? 'HTTP '.$response->status()
                : 'HTTP '.$response->status().' — request failed';

            $api->update([
                'cached_data' => $response->successful() ? $response->json() : null,
                'last_fetched_at' => now(),
            ]);
        } catch (Throwable) {
            $this->status = 'error';
            $this->summary = 'Could not reach '.$api->base_url;
        }
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
    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $summary }}</p>
</div>
