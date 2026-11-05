<?php

use App\Models\Card;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Livewire\Component;

new class extends Component
{
    public Card $card;

    public ?string $output = null;

    public ?int $exitCode = null;

    public function mount(): void
    {
        $this->output = $this->card->output?->last_output;
        $this->exitCode = $this->card->output?->last_exit_code;
    }

    public function run(): void
    {
        $cardOutput = $this->card->output()->first();

        if (! $cardOutput || config('homie.demo_mode')) {
            $this->output = 'Triggers are disabled in demo mode.';
            $this->exitCode = -1;

            return;
        }

        try {
            $result = Process::timeout(30)->run($cardOutput->command);

            $output = trim($result->output()) !== '' ? $result->output() : $result->errorOutput();

            $this->output = trim($output);
            $this->exitCode = $result->exitCode();
        } catch (ProcessTimedOutException) {
            $this->output = 'Command timed out after 30s.';
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
    }
};
?>

<div class="mt-3">
    <button
        type="button"
        wire:click="run"
        wire:confirm="Run “{{ $card->name }}”?"
        wire:loading.attr="disabled"
        wire:target="run"
        class="w-full rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:opacity-50 dark:bg-slate-200 dark:text-slate-900 dark:hover:bg-slate-300"
    >
        <span wire:loading.remove wire:target="run">Run</span>
        <span wire:loading wire:target="run">Running…</span>
    </button>
    @if ($exitCode !== null)
        <div class="mt-2 flex items-start gap-1.5">
            <span @class([
                'mt-1 h-2 w-2 shrink-0 rounded-full',
                'bg-emerald-500' => $exitCode === 0,
                'bg-rose-500' => $exitCode !== 0,
            ])></span>
            <pre class="max-h-24 min-w-0 flex-1 overflow-auto whitespace-pre-wrap font-mono text-xs text-slate-500 dark:text-slate-400">{{ $output !== '' ? $output : 'Done.' }}</pre>
        </div>
    @endif
</div>
