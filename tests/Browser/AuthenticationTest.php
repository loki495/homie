<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('redirects a guest to the login page in a real browser', function () {
    $page = visit('/');

    $page->assertPathIs('/login');
});

it('logs in through the real login form and reaches the dashboard', function () {
    User::factory()->create([
        'email' => 'owner@example.com',
        'password' => Hash::make('a-strong-password'),
    ]);

    $page = visit('/login');

    $page->fill('email', 'owner@example.com')
        ->fill('password', 'a-strong-password')
        ->click('Log in')
        ->assertPathIs('/')
        ->assertDontSee('Log in');
});

/**
 * A real-browser, two-request version of the demo-mode login flow covered at
 * the Feature level in tests/Feature/DemoModeTest.php. This one specifically
 * exists to catch middleware-*ordering* bugs Livewire::test() can never
 * reproduce (it bypasses the HTTP middleware pipeline entirely) - see
 * bootstrap/app.php's comment on why ResolveDemoDatabase must be prepended
 * (run before Laravel's own StartSession) rather than appended. Caught live:
 * appending it let the session handler resolve its DB connection against the
 * pre-repoint database, so a successful login never carried its auth state to
 * the next request - this test fails exactly the way that bug looked (stuck
 * on /login after a real click) if that ordering regresses.
 */
it('logs in on the demo site across two real requests and reaches the dashboard', function () {
    // Same RefreshDatabase/DB::purge('sqlite') correction tests/Feature/
    // DemoModeTest.php needs and documents in full - this test triggers the
    // exact same purge via a real request.
    $this->beforeApplicationDestroyed(function () {
        $pdo = RefreshDatabaseState::$inMemoryConnections['sqlite'] ?? null;

        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        RefreshDatabaseState::$migrated = true;
    });

    $tempDir = sys_get_temp_dir().'/homie-demo-browser-test-'.uniqid();
    mkdir($tempDir, recursive: true);
    $templatePath = "{$tempDir}/template.sqlite";
    $storagePath = "{$tempDir}/demo-dbs";

    touch($templatePath);
    config(['database.connections.sqlite_demo_template' => [
        'driver' => 'sqlite',
        'database' => $templatePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);
    Artisan::call('migrate', ['--database' => 'sqlite_demo_template', '--force' => true]);
    DB::connection('sqlite_demo_template')->table('users')->insert([
        'name' => 'Demo',
        'email' => 'demo@example.com',
        'password' => Hash::make('secret-password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::purge('sqlite_demo_template');

    config([
        'homie.demo_mode' => true,
        'homie.demo_db_template_path' => $templatePath,
        'homie.demo_db_storage_path' => $storagePath,
        'homie.demo_admin_email' => 'demo@example.com',
        'homie.demo_admin_password' => 'secret-password',
    ]);

    try {
        $page = visit('/login');

        $page->fill('email', 'demo@example.com')
            ->fill('password', 'secret-password')
            ->click('Log in')
            ->assertPathIs('/')
            ->assertDontSee('Log in');
    } finally {
        config(['homie.demo_mode' => false]);
        array_map(unlink(...), glob("{$storagePath}/*") ?: []);
        @rmdir($storagePath);
        @unlink($templatePath);
        @rmdir($tempDir);
    }
});
