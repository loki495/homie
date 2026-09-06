<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DemoDashboardSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Builds (or rebuilds) the demo database template that ResolveDemoDatabase
 * copies for every new demo visitor - see config('homie.demo_db_template_path').
 * Never regenerated automatically: homie's demo data (cards/groups/machines)
 * isn't date-sensitive, so there's no staleness reason to run this on a
 * schedule the way insights' equivalent needs to be. Run manually whenever the
 * demo dataset itself should change.
 */
class BuildDemoTemplate extends Command
{
    protected $signature = 'demo:build-template';

    protected $description = 'Build the demo database template (migrate + seed + one Basic Auth user)';

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

        User::query()->firstOrCreate(
            ['email' => config('homie.demo_basic_auth_email')],
            ['name' => 'Demo', 'password' => config('homie.demo_basic_auth_password')],
        );

        $this->info('Demo template built.');

        return self::SUCCESS;
    }
}
