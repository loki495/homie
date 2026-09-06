<?php

declare(strict_types=1);

use App\Http\Middleware\ResolveDemoDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/homie-demo-test-'.uniqid();
    mkdir($this->tempDir, recursive: true);

    $this->templatePath = $this->tempDir.'/template.sqlite';
    file_put_contents($this->templatePath, 'fake-sqlite-bytes');

    $this->storagePath = $this->tempDir.'/demo-dbs';

    $this->originalSqlitePath = config('database.connections.sqlite.database');

    config([
        'homie.demo_mode' => true,
        'homie.demo_db_template_path' => $this->templatePath,
        'homie.demo_db_storage_path' => $this->storagePath,
    ]);

    // The middleware under test purges the 'sqlite' connection whenever it
    // repoints its database file. RefreshDatabase (active suite-wide, see
    // tests/Pest.php) began this test's transaction on a specific shared
    // in-memory PDO tracked in RefreshDatabaseState::$inMemoryConnections,
    // and expects to roll back *that exact* PDO at teardown - but by then
    // 'sqlite' resolves to whatever connection the middleware last created
    // instead, so Laravel's own rollback callback rolls back the wrong
    // object, leaves the real shared PDO's transaction dangling open, and
    // (seeing what looks like an already-rolled-back connection) marks the
    // database as unmigrated for the next test. That combination made every
    // later test in the suite fail trying to re-migrate a database that was
    // still mid-transaction. Fix it directly: roll back the actual shared
    // PDO ourselves and confirm the migrated flag, in a callback registered
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

    array_map(unlink(...), glob($this->storagePath.'/*') ?: []);
    @rmdir($this->storagePath);
    array_map(unlink(...), glob($this->tempDir.'/*') ?: []);
    @rmdir($this->tempDir);
});

it('does nothing when demo mode is off', function () {
    config(['homie.demo_mode' => false]);
    $before = config('database.connections.sqlite.database');

    $response = (new ResolveDemoDatabase)->handle(Request::create('/'), fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok')
        ->and(config('database.connections.sqlite.database'))->toBe($before)
        ->and(Cookie::hasQueued('demo_instance_id'))->toBeFalse();
});

it('creates a new demo id cookie and copies the template on first visit', function () {
    (new ResolveDemoDatabase)->handle(Request::create('/'), fn () => new Response('ok'));

    expect(Cookie::hasQueued('demo_instance_id'))->toBeTrue();

    $demoId = Cookie::queued('demo_instance_id')->getValue();

    expect($demoId)->toMatch('/^[a-zA-Z0-9]{40}$/')
        ->and(file_exists("{$this->storagePath}/{$demoId}.sqlite"))->toBeTrue()
        ->and(file_get_contents("{$this->storagePath}/{$demoId}.sqlite"))->toBe('fake-sqlite-bytes')
        ->and(config('database.connections.sqlite.database'))->toBe("{$this->storagePath}/{$demoId}.sqlite");
});

it('reuses the existing per-session file for a visitor with a valid cookie already', function () {
    $demoId = str_repeat('a', 40);
    mkdir($this->storagePath, recursive: true);
    $existingPath = "{$this->storagePath}/{$demoId}.sqlite";
    file_put_contents($existingPath, 'already-has-data');

    $request = Request::create('/');
    $request->cookies->set('demo_instance_id', $demoId);

    (new ResolveDemoDatabase)->handle($request, fn () => new Response('ok'));

    expect(file_get_contents($existingPath))->toBe('already-has-data')
        ->and(Cookie::hasQueued('demo_instance_id'))->toBeFalse()
        ->and(config('database.connections.sqlite.database'))->toBe($existingPath);
});

it('treats a malformed cookie value as a new visitor rather than trusting it', function () {
    $request = Request::create('/');
    $request->cookies->set('demo_instance_id', '../../etc/passwd');

    (new ResolveDemoDatabase)->handle($request, fn () => new Response('ok'));

    expect(Cookie::hasQueued('demo_instance_id'))->toBeTrue()
        ->and(Cookie::queued('demo_instance_id')->getValue())->toMatch('/^[a-zA-Z0-9]{40}$/');
});

it('aborts with a server error when the template file is missing', function () {
    config(['homie.demo_db_template_path' => "{$this->tempDir}/does-not-exist.sqlite"]);

    (new ResolveDemoDatabase)->handle(Request::create('/'), fn () => new Response('ok'));
})->throws(HttpException::class);
