<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature-level test of the two demo-mode middlewares (ResolveDemoDatabase +
 * RequireBasicAuthInDemoMode) actually composing together against a real route,
 * rather than each in isolation - this is what a real visitor experiences.
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

it('blocks the demo site behind Basic Auth with no credentials', function () {
    $this->get('/')->assertStatus(401);
});

it('still blocks a request with no Cloudflare headers when demo_trust_lan is off (default)', function () {
    config(['homie.demo_trust_lan' => false]);

    $this->get('/')->assertStatus(401);
});

it('skips Basic Auth for a request with no Cloudflare headers when demo_trust_lan is on', function () {
    /** @var TestCase $this */
    $this->withoutVite();
    config(['homie.demo_trust_lan' => true]);

    $this->call('GET', '/', server: ['REMOTE_ADDR' => '192.168.1.50'])->assertStatus(200);
});

it('does not skip Basic Auth via demo_trust_lan for a non-private client IP even with no Cloudflare headers', function () {
    /** @var TestCase $this */
    config(['homie.demo_trust_lan' => true]);

    $this->call('GET', '/', server: ['REMOTE_ADDR' => '8.8.8.8'])->assertStatus(401);
});

it('does not skip Basic Auth via demo_trust_lan for a request that went through Cloudflare', function () {
    config(['homie.demo_trust_lan' => true]);

    $this->withHeaders(['CF-Connecting-IP' => '1.2.3.4'])
        ->get('/')
        ->assertStatus(401);
});

it('skips Basic Auth when Cloudflare Access asserts the configured owner email', function () {
    /** @var TestCase $this */
    $this->withoutVite();
    config(['homie.demo_owner_email' => 'owner@example.com']);

    $this->withHeaders([
        'CF-Connecting-IP' => '1.2.3.4',
        'Cf-Access-Authenticated-User-Email' => 'owner@example.com',
    ])->get('/')->assertStatus(200);
});

it('does not skip Basic Auth when the asserted Cloudflare identity does not match the owner email', function () {
    config(['homie.demo_owner_email' => 'owner@example.com']);

    $this->withHeaders([
        'CF-Connecting-IP' => '1.2.3.4',
        'Cf-Access-Authenticated-User-Email' => 'someone-else@example.com',
    ])->get('/')->assertStatus(401);
});

it('does not skip Basic Auth from a client-supplied Cloudflare identity header when no owner email is configured', function () {
    config(['homie.demo_owner_email' => null]);

    $this->withHeaders([
        'CF-Connecting-IP' => '1.2.3.4',
        'Cf-Access-Authenticated-User-Email' => 'anyone@example.com',
    ])->get('/')->assertStatus(401);
});

it('rejects the wrong password', function () {
    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('demo@example.com:wrong-password')])
        ->get('/')
        ->assertStatus(401);
});

it('allows access with the correct demo credentials and isolates the visitor to their own copy', function () {
    // home.blade.php is the only view that renders @vite, and no built
    // public/build/manifest.json exists in a fresh CI checkout - same reason
    // HomeTest.php disables it (see this repo's own CLAUDE.md "Testing" section).
    /** @var TestCase $this */
    $this->withoutVite();

    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('demo@example.com:secret-password')])
        ->get('/')
        ->assertStatus(200);

    // One private per-visitor copy was made; the template itself is untouched.
    expect(glob("{$this->storagePath}/*.sqlite"))->toHaveCount(1)
        ->and(filesize($this->templatePath))->toBe(filesize(current(glob("{$this->storagePath}/*.sqlite") ?: [''])));
});
