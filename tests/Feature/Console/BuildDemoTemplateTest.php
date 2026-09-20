<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/homie-build-template-test-'.uniqid();
    mkdir($this->tempDir, recursive: true);

    $this->templatePath = $this->tempDir.'/template.sqlite';
    $this->originalSqlitePath = config('database.connections.sqlite.database');

    // The command under test purges the 'sqlite' connection whenever it repoints
    // its database file - see ResolveDemoDatabaseTest.php for why RefreshDatabase's
    // own rollback needs this same correction, or later tests fail re-migrating a
    // database still mid-transaction.
    $this->beforeApplicationDestroyed(function () {
        $pdo = RefreshDatabaseState::$inMemoryConnections['sqlite'] ?? null;

        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        RefreshDatabaseState::$migrated = true;
    });
});

afterEach(function () {
    config(['database.connections.sqlite.database' => $this->originalSqlitePath]);
    array_map(unlink(...), glob($this->tempDir.'/*') ?: []);
    @rmdir($this->tempDir);
});

it('migrates, seeds, and provisions the shared demo admin at the configured path', function () {
    config([
        'homie.demo_db_template_path' => $this->templatePath,
        'homie.demo_mode' => true,
    ]);

    $exitCode = Artisan::call('demo:build-template');

    expect($exitCode)->toBe(0)
        ->and(file_exists($this->templatePath))->toBeTrue();

    $pdo = new PDO('sqlite:'.$this->templatePath);
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cards'")->fetchAll();

    expect($tables)->not->toBeEmpty();

    config(['database.connections.sqlite.database' => $this->templatePath]);
    DB::purge('sqlite');

    expect(User::query()->where('email', config('homie.demo_admin_email'))->exists())->toBeTrue();
});

it('fails without touching any database when the template path is not configured', function () {
    config(['homie.demo_db_template_path' => null]);

    $exitCode = Artisan::call('demo:build-template');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('not configured');
});

it('fails without touching any database when the template path is an empty string', function () {
    config(['homie.demo_db_template_path' => '']);

    $exitCode = Artisan::call('demo:build-template');

    expect($exitCode)->toBe(1);
});
