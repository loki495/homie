<?php

use App\Support\Config\ConfigExporter;
use App\Support\Config\ConfigImporter;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    public ?string $importError = null;

    public ?string $importSummary = null;

    /** @var list<string> */
    public array $importWarnings = [];

    public function export(): StreamedResponse
    {
        $json = json_encode(app(ConfigExporter::class)->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            abort(500, 'Could not build the export.');
        }

        return response()->streamDownload(
            function () use ($json): void {
                echo $json;
            },
            'homie-backup-'.now()->format('Y-m-d-His').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function import(string $json): void
    {
        $this->reset(['importError', 'importSummary', 'importWarnings']);

        $data = json_decode($json, true);

        if (! is_array($data)) {
            $this->importError = 'That file is not valid JSON.';

            return;
        }

        $result = app(ConfigImporter::class)->import($data);

        $this->importSummary = "Imported {$result->groups} group(s), {$result->cards} card(s), {$result->machines} machine(s).";
        $this->importWarnings = $result->warnings;

        $this->dispatch('dashboard-updated');
    }
};
?>

<div class="space-y-6">
    <div class="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
        <flux:heading size="sm">Export</flux:heading>
        <p class="text-sm text-slate-400 dark:text-slate-500">
            Downloads every group, card, and scan target as JSON — a backup, or something to carry over to a new
            box. API keys, passwords, and SSH private keys are never included; you'll re-enter those after an
            import.
        </p>
        <flux:button wire:click="export" variant="primary" class="w-full">Download backup</flux:button>
    </div>

    <div
        x-data="{
            onFileChange(event) {
                const file = event.target.files[0];
                if (! file) return;
                if (! confirm('Importing replaces every group, card, and scan target currently on this dashboard with the contents of this file. Continue?')) {
                    event.target.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = () => { $wire.import(reader.result); event.target.value = ''; };
                reader.readAsText(file);
            },
        }"
        class="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700"
    >
        <flux:heading size="sm">Import</flux:heading>
        <p class="text-sm text-slate-400 dark:text-slate-500">
            Replaces everything on this dashboard with the contents of the file below — groups, cards, and scan
            targets you have now are deleted first.
        </p>
        <input
            type="file"
            accept="application/json"
            x-on:change="onFileChange"
            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-slate-600 dark:text-slate-300 dark:file:bg-slate-700 dark:file:text-slate-200"
        />

        @if ($importError)
            <p class="text-sm text-rose-600 dark:text-rose-400">{{ $importError }}</p>
        @endif

        @if ($importSummary)
            <p class="text-sm text-emerald-600 dark:text-emerald-400">{{ $importSummary }}</p>

            @if ($importWarnings !== [])
                <ul class="list-disc space-y-1 pl-5 text-sm text-amber-600 dark:text-amber-400">
                    @foreach ($importWarnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>
</div>
