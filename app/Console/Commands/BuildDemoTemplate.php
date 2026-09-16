<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\DemoDashboardSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Builds (or rebuilds) the demo database template that ResolveDemoDatabase
 * copies for every new demo visitor - see config('homie.demo_db_template_path').
 * Not scheduled - homie's demo data (cards/groups/machines) isn't date-sensitive,
 * so there's no staleness reason to run this on a timer the way insights'
 * equivalent needs to be. Run on every container boot instead (see
 * docker/entrypoint-prod.sh), which is what actually keeps each demo deploy
 * stateless - a fresh template every start, no volume required for demo data.
 */
class BuildDemoTemplate extends Command
{
    protected $signature = 'demo:build-template';

    protected $description = 'Build the demo database template (migrate + seed + the shared demo login)';

    public function handle(): int
    {
        $path = config('homie.demo_db_template_path');

        if (! is_string($path) || $path === '') {
            $this->error('homie.demo_db_template_path is not configured.');

            return self::FAILURE;
        }

        config(['database.connections.sqlite.database' => $path]);
        DB::purge('sqlite');

        $this->info("Building demo template at {$path}");

        Artisan::call('migrate:fresh', ['--force' => true], $this->output);
        Artisan::call('db:seed', ['--class' => DemoDashboardSeeder::class, '--force' => true], $this->output);

        // Bare (no --email/--password) so MakeAdminUser takes its demo-mode
        // shortcut and provisions demo_admin_email/demo_admin_password
        // non-interactively, instead of duplicating that logic here.
        Artisan::call('homie:make-admin', [], $this->output);

        $this->info('Demo template built.');

        return self::SUCCESS;
    }
}
