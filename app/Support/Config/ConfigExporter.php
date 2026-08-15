<?php

declare(strict_types=1);

namespace App\Support\Config;

use App\Models\Card;
use App\Models\Group;
use App\Models\Machine;

/**
 * Serializes every group/card/machine into a plain-array config shape, for the
 * Backup tab's "Export" download. Deliberately excludes secrets (card_apis.api_key/
 * password, machines.ssh_private_key) and runtime/cache data (last_output,
 * cached_data, last_fetched_at, ...) — this is a config backup, not a full table
 * dump. Secret fields are replaced with a `has_*` boolean so ConfigImporter can
 * tell the user which cards/machines need credentials re-entered after import.
 *
 * Built with explicit foreach loops rather than Collection::map() chains — the
 * latter left Larastan unable to resolve the closures' return types here (same
 * class of issue documented in MachineDiscovery/DashboardIcons for collect() on
 * loosely-typed data).
 */
class ConfigExporter
{
    public const int VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function export(): array
    {
        $groups = Group::query()
            ->with(['cards' => fn ($query) => $query->orderBy('sort_order')->with(['output', 'api'])])
            ->orderBy('sort_order')
            ->get();

        $exportedGroups = [];

        foreach ($groups as $group) {
            $cards = [];

            foreach ($group->cards as $card) {
                $cards[] = $this->exportCard($card);
            }

            $exportedGroups[] = [
                'name' => $group->name,
                'sort_order' => $group->sort_order,
                'collapsed' => $group->collapsed,
                'cards' => $cards,
            ];
        }

        $ungroupedCards = Card::query()
            ->whereNull('group_id')
            ->orderBy('sort_order')
            ->with(['output', 'api'])
            ->get();

        $exportedUngroupedCards = [];

        foreach ($ungroupedCards as $card) {
            $exportedUngroupedCards[] = $this->exportCard($card);
        }

        $machines = Machine::query()->orderBy('name')->get();
        $exportedMachines = [];

        foreach ($machines as $machine) {
            $exportedMachines[] = [
                'name' => $machine->name,
                'host' => $machine->host,
                'description' => $machine->description,
                'discovery_method' => $machine->discovery_method->value,
                'ssh_user' => $machine->ssh_user,
                'ssh_port' => $machine->ssh_port,
                'has_ssh_private_key' => $machine->ssh_private_key !== null,
            ];
        }

        return [
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'groups' => $exportedGroups,
            'ungrouped_cards' => $exportedUngroupedCards,
            'machines' => $exportedMachines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exportCard(Card $card): array
    {
        return [
            'name' => $card->name,
            'type' => $card->type->value,
            'icon' => $card->icon,
            'url' => $card->url,
            'sort_order' => $card->sort_order,
            'output' => $card->output ? [
                'command' => $card->output->command,
                'refresh_interval_seconds' => $card->output->refresh_interval_seconds,
            ] : null,
            'api' => $card->api ? [
                'provider' => $card->api->provider->value,
                'base_url' => $card->api->base_url,
                'auth_type' => $card->api->auth_type,
                'username' => $card->api->username,
                'has_api_key' => $card->api->api_key !== null,
                'has_password' => $card->api->password !== null,
            ] : null,
        ];
    }
}
