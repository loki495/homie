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
        Machine::create([
            'name' => 'nas',
            'host' => 'nas.lan',
            'description' => 'Example saved scan target for the "Discover" action in Settings.',
        ]);

        $mediaGroup = Group::create(['name' => 'Media', 'sort_order' => 0]);
        $systemGroup = Group::create(['name' => 'System', 'sort_order' => 1]);

        Card::create([
            'group_id' => $mediaGroup->id,
            'name' => 'Plex',
            'type' => CardType::Link,
            'url' => 'http://media-server.lan:32400/web',
            'sort_order' => 0,
        ]);

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

        // Demo mode only (config('homie.demo_mode')) - this seeder is also run
        // manually for plain local-dev sample data (see the class docblock), and
        // the mock arr-stack service this points at only exists in the demo
        // compose stack (docker-compose.yml's mock-sonarr, --profile demo), so
        // gating this keeps a non-demo run of this same seeder unaffected. Proves
        // the mock-API feature works passively: a visitor sees a live-looking
        // "Sonarr" card with real fetched stats with no action needed.
        if (config('homie.demo_mode')) {
            Card::create([
                'group_id' => $mediaGroup->id,
                'name' => 'Sonarr',
                'type' => CardType::Api,
                'url' => (string) config('homie.demo_mock_sonarr_url'),
                'sort_order' => 2,
            ])->api()->create([
                'provider' => ApiProvider::Sonarr,
                'base_url' => (string) config('homie.demo_mock_sonarr_url'),
                'api_key' => 'demo-api-key',
            ]);
        }
    }
}
