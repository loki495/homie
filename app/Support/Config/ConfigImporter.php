<?php

declare(strict_types=1);

namespace App\Support\Config;

use App\Enums\ApiProvider;
use App\Enums\CardType;
use App\Enums\DiscoveryMethod;
use App\Models\Card;
use App\Models\CardApi;
use App\Models\CardOutput;
use App\Models\Group;
use App\Models\Machine;
use Illuminate\Support\Facades\DB;

/**
 * Replaces every group/card/machine with the contents of a previously-exported
 * config (see ConfigExporter). This is a full restore, not a merge — existing
 * groups/cards/machines are deleted first, inside one transaction, so a restore
 * never leaves a half-imported mix of old and new data. Secrets were never in the
 * export, so cards/machines that had one keep `has_api_key`/`has_password`/
 * `has_ssh_private_key` true on the way in but the field itself comes back null —
 * the Backup tab surfaces that count so the user knows to re-enter them.
 *
 * Rows are validated defensively rather than via Laravel's validator: this reads
 * a user-supplied file that may be hand-edited, from an older export version, or
 * just malformed, and a single bad row shouldn't abort the whole restore. Anything
 * that doesn't look right is skipped with a warning instead of thrown.
 */
class ConfigImporter
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function import(array $data): ImportResult
    {
        $groupsData = is_array($data['groups'] ?? null) ? $data['groups'] : [];
        $ungroupedData = is_array($data['ungrouped_cards'] ?? null) ? $data['ungrouped_cards'] : [];
        $machinesData = is_array($data['machines'] ?? null) ? $data['machines'] : [];

        $warnings = [];
        $groupCount = 0;
        $cardCount = 0;
        $machineCount = 0;

        DB::transaction(function () use ($groupsData, $ungroupedData, $machinesData, &$warnings, &$groupCount, &$cardCount, &$machineCount): void {
            Card::query()->delete();
            Group::query()->delete();
            Machine::query()->delete();

            foreach ($groupsData as $index => $groupRow) {
                if (! is_array($groupRow) || ! is_string($groupRow['name'] ?? null) || $groupRow['name'] === '') {
                    $warnings[] = "Skipped group at index {$index}: missing a name.";

                    continue;
                }

                $group = Group::create([
                    'name' => $groupRow['name'],
                    'sort_order' => is_int($groupRow['sort_order'] ?? null) ? $groupRow['sort_order'] : $index,
                    'collapsed' => (bool) ($groupRow['collapsed'] ?? false),
                ]);
                $groupCount++;

                $cardsData = is_array($groupRow['cards'] ?? null) ? $groupRow['cards'] : [];

                foreach ($cardsData as $cardIndex => $cardRow) {
                    if ($this->importCard($cardRow, $group->id, $cardIndex, $warnings)) {
                        $cardCount++;
                    }
                }
            }

            foreach ($ungroupedData as $cardIndex => $cardRow) {
                if ($this->importCard($cardRow, null, $cardIndex, $warnings)) {
                    $cardCount++;
                }
            }

            foreach ($machinesData as $index => $machineRow) {
                if (! is_array($machineRow) || ! is_string($machineRow['name'] ?? null) || $machineRow['name'] === ''
                    || ! is_string($machineRow['host'] ?? null) || $machineRow['host'] === '') {
                    $warnings[] = "Skipped machine at index {$index}: missing a name or host.";

                    continue;
                }

                $discoveryMethod = DiscoveryMethod::tryFrom((string) ($machineRow['discovery_method'] ?? ''));

                if (! $discoveryMethod) {
                    $warnings[] = "Skipped machine \"{$machineRow['name']}\": unknown discovery method.";

                    continue;
                }

                Machine::create([
                    'name' => $machineRow['name'],
                    'host' => $machineRow['host'],
                    'description' => is_string($machineRow['description'] ?? null) ? $machineRow['description'] : null,
                    'discovery_method' => $discoveryMethod,
                    'ssh_user' => is_string($machineRow['ssh_user'] ?? null) ? $machineRow['ssh_user'] : null,
                    'ssh_port' => is_int($machineRow['ssh_port'] ?? null) ? $machineRow['ssh_port'] : null,
                ]);
                $machineCount++;

                if ($machineRow['has_ssh_private_key'] ?? false) {
                    $warnings[] = "Machine \"{$machineRow['name']}\" needs its SSH private key re-entered (not included in the export).";
                }
            }
        });

        return new ImportResult($groupCount, $cardCount, $machineCount, $warnings);
    }

    /**
     * @param  list<string>  $warnings  Appended to by reference.
     */
    private function importCard(mixed $cardRow, ?int $groupId, int $index, array &$warnings): bool
    {
        if (! is_array($cardRow) || ! is_string($cardRow['name'] ?? null) || $cardRow['name'] === '') {
            $warnings[] = "Skipped card at index {$index}: missing a name.";

            return false;
        }

        $type = CardType::tryFrom((string) ($cardRow['type'] ?? ''));

        if (! $type) {
            $warnings[] = "Skipped card \"{$cardRow['name']}\": unknown card type.";

            return false;
        }

        $card = Card::create([
            'group_id' => $groupId,
            'name' => $cardRow['name'],
            'type' => $type,
            'icon' => is_string($cardRow['icon'] ?? null) ? $cardRow['icon'] : null,
            'url' => is_string($cardRow['url'] ?? null) ? $cardRow['url'] : null,
            'sort_order' => is_int($cardRow['sort_order'] ?? null) ? $cardRow['sort_order'] : $index,
        ]);

        $outputRow = $cardRow['output'] ?? null;

        if (is_array($outputRow) && is_string($outputRow['command'] ?? null) && $outputRow['command'] !== '') {
            CardOutput::create([
                'card_id' => $card->id,
                'command' => $outputRow['command'],
                'refresh_interval_seconds' => is_int($outputRow['refresh_interval_seconds'] ?? null)
                    ? $outputRow['refresh_interval_seconds']
                    : null,
            ]);
        }

        $apiRow = $cardRow['api'] ?? null;

        if (is_array($apiRow)) {
            $provider = ApiProvider::tryFrom((string) ($apiRow['provider'] ?? ''));
            $authType = in_array($apiRow['auth_type'] ?? null, ['api_key', 'basic'], true) ? $apiRow['auth_type'] : null;

            if ($provider && $authType && is_string($apiRow['base_url'] ?? null) && $apiRow['base_url'] !== '') {
                CardApi::create([
                    'card_id' => $card->id,
                    'provider' => $provider,
                    'base_url' => $apiRow['base_url'],
                    'auth_type' => $authType,
                    'username' => is_string($apiRow['username'] ?? null) ? $apiRow['username'] : null,
                ]);

                if ($apiRow['has_api_key'] ?? false) {
                    $warnings[] = "Card \"{$cardRow['name']}\" needs its API key re-entered (not included in the export).";
                }

                if ($apiRow['has_password'] ?? false) {
                    $warnings[] = "Card \"{$cardRow['name']}\" needs its password re-entered (not included in the export).";
                }
            } else {
                $warnings[] = "Card \"{$cardRow['name']}\": skipped its API connection, missing or invalid fields.";
            }
        }

        return true;
    }
}
