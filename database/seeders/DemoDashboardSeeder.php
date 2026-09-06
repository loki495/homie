<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApiProvider;
use App\Enums\CardType;
use App\Enums\DiscoveryMethod;
use App\Models\Card;
use App\Models\Group;
use App\Models\Machine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

            // Skipped (not just given a useless placeholder) if the private key
            // was never configured for this deployment - see config/homie.php's
            // "Demo output-card SSH sandbox" section for why it's never
            // committed/defaulted. A visitor can't use the output-card feature
            // hands-on without this, but the rest of demo mode still works fine.
            if (config('homie.demo_sandbox_ssh_private_key')) {
                $sandboxMachine = Machine::create([
                    'name' => 'Demo sandbox',
                    'host' => (string) config('homie.demo_sandbox_host'),
                    'description' => 'Locked-down SSH target for trying the output-card feature - see docker/ssh-sandbox/README.md. Only uptime, df -h, date, whoami, and echo <text> are allowed.',
                    'discovery_method' => DiscoveryMethod::Ssh,
                    'ssh_user' => (string) config('homie.demo_sandbox_ssh_user'),
                    'ssh_port' => (int) config('homie.demo_sandbox_port'),
                    'ssh_private_key' => (string) config('homie.demo_sandbox_ssh_private_key'),
                ]);

                // Output cards run a raw shell command, not a structured
                // "pick a machine" reference (see homie's CLAUDE.md, "Container
                // / infra" - storage/ssh/ holds keys the user's own command text
                // is expected to reference directly). MachineObserver already
                // synced the key to storage/ssh/{slug} the moment the Machine
                // above was created - same ssh options homie's own
                // MachineDiscovery::sshCommand() uses, so this is exactly the
                // pattern a real user would write by hand, not a shortcut.
                $slug = Str::slug($sandboxMachine->name);
                Card::create([
                    'group_id' => $systemGroup->id,
                    'name' => 'Try: uptime',
                    'type' => CardType::Output,
                    'sort_order' => 2,
                ])->output()->create([
                    'command' => sprintf(
                        'ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=/dev/null -o ConnectTimeout=5 -i %s -p %d %s@%s uptime',
                        escapeshellarg(config('homie.ssh_key_path')."/{$slug}"),
                        (int) config('homie.demo_sandbox_port'),
                        (string) config('homie.demo_sandbox_ssh_user'),
                        (string) config('homie.demo_sandbox_host'),
                    ),
                ]);
            }
        }
    }
}
