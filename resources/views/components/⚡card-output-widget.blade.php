<?php

use App\Models\Card;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Card $card;

    public ?string $output = null;

    public ?int $exitCode = null;

    public ?int $refreshIntervalSeconds = null;

    #[On('dashboard-updated')]
    public function refreshCard(): void
    {
        $this->card = $this->card->fresh();
        $this->refreshIntervalSeconds = $this->card->output?->refresh_interval_seconds;
    }

    public function mount(): void
    {
        $this->runCommand();
    }

    public function refreshOutput(): void
    {
        $this->runCommand();
    }

    private function runCommand(): void
    {
        $cardOutput = $this->card->output()->first();

        if (! $cardOutput) {
            return;
        }

        $this->refreshIntervalSeconds = $cardOutput->refresh_interval_seconds;
        $this->dispatch('output-refreshed');

        $lock = Cache::lock("card-output-running:{$cardOutput->id}", 15);

        if (! $lock->get()) {
            $this->output = $cardOutput->last_output;
            $this->exitCode = $cardOutput->last_exit_code;

            return;
        }

        try {
            try {
                $result = Process::timeout(10)->run($cardOutput->command);

                $output = trim($result->output()) !== '' ? $result->output() : $result->errorOutput();

                $this->output = trim($output);
                $this->exitCode = $result->exitCode();
            } catch (ProcessTimedOutException) {
                $this->output = 'Command timed out after 10s.';
                $this->exitCode = -1;
            } catch (Throwable $e) {
                $this->output = 'Command failed: '.$e->getMessage();
                $this->exitCode = -1;
            }

            $cardOutput->update([
                'last_output' => $this->output,
                'last_exit_code' => $this->exitCode,
                'last_run_at' => now(),
            ]);
        } finally {
            $lock->release();
        }
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="animate-pulse">
                <div class="ml-auto mt-1 h-3 w-10 rounded bg-slate-100 dark:bg-slate-700/50"></div>
                <div class="mt-2 h-16 rounded bg-slate-100 dark:bg-slate-700/50"></div>
            </div>
        HTML;
    }
};
?>

<div @if ($refreshIntervalSeconds) wire:poll.{{ $refreshIntervalSeconds }}s="refreshOutput" @endif>
    <div class="flex items-center justify-end gap-1.5">
        @if ($refreshIntervalSeconds)
            <span
                x-data="{ remaining: {{ $refreshIntervalSeconds }} }"
                x-init="
                    $wire.on('output-refreshed', () => { remaining = {{ $refreshIntervalSeconds }} });
                    setInterval(() => { remaining = remaining > 0 ? remaining - 1 : 0 }, 1000);
                "
                wire:loading.remove
                wire:target="refreshOutput"
                class="-translate-y-[2px] inline-block leading-none text-xs tabular-nums text-slate-400 dark:text-slate-500"
                x-text="remaining + 's'"
            ></span>
            <svg
                wire:loading
                wire:target="refreshOutput"
                class="-translate-y-[2px] block h-3 w-3 shrink-0 animate-spin text-slate-400 dark:text-slate-500"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        @endif
        @if ($exitCode !== null)
            <span @class([
                '-translate-y-px h-2 w-2 rounded-full',
                'bg-emerald-500' => $exitCode === 0,
                'bg-rose-500' => $exitCode !== 0,
            ])></span>
        @endif
    </div>
    <pre class="mt-2 max-h-40 overflow-auto whitespace-pre font-mono text-xs text-slate-500 dark:text-slate-400">{{ $output ?? 'No output yet.' }}</pre>
</div>
