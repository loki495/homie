<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature-level test of ResolveDemoDatabase + RequireAuthentication actually
 * composing together against a real route, rather than each in isolation -
 * this is what a real visitor experiences. Demo mode used to gate access with
 * HTTP Basic Auth (RequireBasicAuthInDemoMode, since removed) - it now reuses
 * the same real login as everywhere else, against a shared admin user seeded
 * into every visitor's own copy of the template (see BuildDemoTemplate).
 */
beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/homie-demo-feature-test-'.uniqid();
    mkdir($this->tempDir, recursive: true);
    $this->templatePath = "{$this->tempDir}/template.sqlite";
    $this->storagePath = "{$this->tempDir}/demo-dbs";

    // Built on its own connection name, deliberately never touching 'sqlite' -
    // that's the connection RefreshDatabase is managing for this test process,
    // and the middleware under test repoints it dynamically per-request anyway.
    touch($this->templatePath);
    config(['database.connections.sqlite_demo_template' => [
        'driver' => 'sqlite',
        'database' => $this->templatePath,
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

    $this->originalSqlitePath = config('database.connections.sqlite.database');

    config([
        'homie.demo_mode' => true,
        'homie.demo_db_template_path' => $this->templatePath,
        'homie.demo_db_storage_path' => $this->storagePath,
    ]);

    // ResolveDemoDatabase purges the 'sqlite' connection whenever it repoints
    // its database file. RefreshDatabase (active suite-wide, see
    // tests/Pest.php) began this test's transaction on a specific shared
    // in-memory PDO tracked in RefreshDatabaseState::$inMemoryConnections,
    // and expects to roll back *that exact* PDO at teardown - but by then
    // 'sqlite' resolves to whatever connection the middleware last created
    // instead, so Laravel's own rollback callback rolls back the wrong
    // object, leaves the real shared PDO's transaction dangling open, and
    // (seeing what looks like an already-rolled-back connection) marks the
    // database as unmigrated for the next test - corrupting every later test
    // in the suite. Fix it directly: roll back the actual shared PDO
    // ourselves and confirm the migrated flag, in a callback registered
    // after (so it runs after) RefreshDatabase's own - see
    // beginDatabaseTransaction() in vendor/laravel/framework's
    // RefreshDatabase trait for the callback this is correcting.
    $this->beforeApplicationDestroyed(function () {
        $pdo = RefreshDatabaseState::$inMemoryConnections['sqlite'] ?? null;

        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        RefreshDatabaseState::$migrated = true;
    });
});

afterEach(function () {
    config([
        'homie.demo_mode' => false,
        'database.connections.sqlite.database' => $this->originalSqlitePath,
    ]);

    array_map(unlink(...), glob("{$this->storagePath}/*") ?: []);
    @rmdir($this->storagePath);
    @unlink($this->templatePath);
    @rmdir($this->tempDir);
});

it('redirects a guest on the demo site to the login page, same as any other deployment', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('prefills the shared demo credentials on the login page itself', function () {
    Livewire::test('login')
        ->assertSet('email', config('homie.demo_admin_email'))
        ->assertSet('password', config('homie.demo_admin_password'));
});

it('rejects the wrong password on the demo site', function () {
    /** @var TestCase $this */
    $this->withoutVite();

    // A real visit first, same as any visitor - this is what actually runs
    // ResolveDemoDatabase and repoints the 'sqlite' connection to a fresh
    // per-visitor copy that Livewire::test() below then operates against.
    $this->get('/');

    Livewire::test('login')
        ->set('email', 'demo@example.com')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

it('logs in with the shared demo credentials and isolates the visitor to their own copy', function () {
    /** @var TestCase $this */
    $this->withoutVite();

    $this->get('/');

    Livewire::test('login')
        ->set('email', 'demo@example.com')
        ->set('password', 'secret-password')
        ->call('login')
        ->assertRedirect('/');

    $this->assertAuthenticated();

    // One private per-visitor copy was made; the template itself is untouched.
    // Per-visitor cookie/copy isolation itself (new cookie -> new copy,
    // existing cookie -> reused copy, malformed cookie -> treated as new) is
    // covered directly against ResolveDemoDatabase in
    // tests/Unit/Http/Middleware/ResolveDemoDatabaseTest.php - this only
    // confirms the real login composes with it correctly, end to end.
    expect(glob("{$this->storagePath}/*.sqlite"))->toHaveCount(1)
        ->and(filesize($this->templatePath))->toBe(filesize(current(glob("{$this->storagePath}/*.sqlite") ?: [''])));
});
