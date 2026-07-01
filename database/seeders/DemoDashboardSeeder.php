<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApiProvider;
use App\Enums\CardType;
use App\Models\Card;
use App\Models\Group;
use App\Models\Machine;
use Illuminate\Database\Seeder;

/**
 * Sample dashboard content for local development only. Never wired into the
 * default DatabaseSeeder — a distributed dashboard ships empty, not pre-loaded
 * with someone else's lab. Run manually: php artisan db:seed --class=DemoDashboardSeeder
 */
class DemoDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $mediaServer = Machine::create([
            'name' => 'media-server',
            'hostname' => 'media-server.lan',
            'description' => 'Example LAN machine used by the demo cards below.',
        ]);

        $nas = Machine::create([
            'name' => 'nas',
            'hostname' => 'nas.lan',
            'description' => 'Example second machine, to demonstrate a service reachable from more than one host.',
        ]);

        $mediaGroup = Group::create(['name' => 'Media', 'sort_order' => 0]);
        $systemGroup = Group::create(['name' => 'System', 'sort_order' => 1]);

        $plex = Card::create([
            'group_id' => $mediaGroup->id,
            'name' => 'Plex',
            'type' => CardType::Link,
            'url' => 'http://media-server.lan:32400/web',
            'sort_order' => 0,
        ]);
        $plex->machines()->attach($mediaServer->id, ['url' => 'http://media-server.lan:32400/web']);
        $plex->machines()->attach($nas->id, ['url' => 'http://nas.lan:32400/web']);

        Card::create([
            'group_id' => $mediaGroup->id,
            'name' => 'NZBGet',
            'type' => CardType::Api,
            'url' => 'http://nas.lan:6789',
            'sort_order' => 1,
        ])->api()->create([
            'provider' => ApiProvider::Nzbget,
            'base_url' => 'http://nas.lan:6789',
            'api_key' => 'replace-with-real-api-key',
        ]);

        Card::create([
            'group_id' => $systemGroup->id,
            'name' => 'Disk space',
            'type' => CardType::Output,
            'sort_order' => 0,
        ])->output()->create([
            'command' => 'df -h /',
        ]);

        Card::create([
            'group_id' => $systemGroup->id,
            'name' => 'Uptime',
            'type' => CardType::Output,
            'sort_order' => 1,
        ])->output()->create([
            'command' => 'uptime',
        ]);

        Card::create([
            'name' => 'Router admin',
            'type' => CardType::Link,
            'url' => 'http://192.168.1.1',
            'sort_order' => 0,
        ]);
    }
}
