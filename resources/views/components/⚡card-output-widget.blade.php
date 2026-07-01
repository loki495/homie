<?php

use App\Models\Card;
use Illuminate\Support\Facades\Process;
use Livewire\Component;

new class extends Component
{
    public Card $card;

    public ?string $output = null;

    public ?int $exitCode = null;

    public function mount(Card $card): void
    {
        $this->card = $card;

        $cardOutput = $card->output;

        if (! $cardOutput) {
            return;
        }

        $result = Process::timeout(10)->run($cardOutput->command);

        $output = trim($result->output()) !== '' ? $result->output() : $result->errorOutput();

        $this->output = trim($output);
        $this->exitCode = $result->exitCode();

        $cardOutput->update([
            'last_output' => $this->output,
            'last_exit_code' => $this->exitCode,
            'last_run_at' => now(),
        ]);
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="animate-pulse rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                <div class="h-4 w-24 rounded bg-slate-200 dark:bg-slate-700"></div>
                <div class="mt-3 h-16 rounded bg-slate-100 dark:bg-slate-700/50"></div>
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
        @if ($exitCode !== null)
            <span @class([
                'h-2 w-2 rounded-full',
                'bg-emerald-500' => $exitCode === 0,
                'bg-rose-500' => $exitCode !== 0,
            ])></span>
        @endif
    </div>
    <pre class="mt-2 max-h-40 overflow-auto whitespace-pre font-mono text-xs text-slate-500 dark:text-slate-400">{{ $output ?? 'No output yet.' }}</pre>
</div>
