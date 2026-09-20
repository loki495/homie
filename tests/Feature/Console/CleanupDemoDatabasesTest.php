<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->storagePath = sys_get_temp_dir().'/homie-demo-cleanup-test-'.uniqid();
});

afterEach(function () {
    array_map(unlink(...), glob($this->storagePath.'/*') ?: []);
    @rmdir($this->storagePath);
});

it('reports nothing to clean when the demo-dbs directory does not exist yet', function () {
    config(['homie.demo_db_storage_path' => $this->storagePath]);

    $exitCode = Artisan::call('demo:cleanup');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('nothing to clean');
});

it('deletes only files older than the retention window and reports the count', function () {
    mkdir($this->storagePath, recursive: true);
    config(['homie.demo_db_storage_path' => $this->storagePath]);

    $stale = "{$this->storagePath}/stale.sqlite";
    $fresh = "{$this->storagePath}/fresh.sqlite";
    file_put_contents($stale, 'old');
    file_put_contents($fresh, 'new');

    touch($stale, time() - (25 * 3600));
    touch($fresh, time() - 3600);

    $exitCode = Artisan::call('demo:cleanup');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Deleted 1 stale demo database file')
        ->and(file_exists($stale))->toBeFalse()
        ->and(file_exists($fresh))->toBeTrue();
});

it('honors a custom --hours retention window', function () {
    mkdir($this->storagePath, recursive: true);
    config(['homie.demo_db_storage_path' => $this->storagePath]);

    $file = "{$this->storagePath}/recent.sqlite";
    file_put_contents($file, 'data');
    touch($file, time() - (2 * 3600));

    $exitCode = Artisan::call('demo:cleanup', ['--hours' => 1]);

    expect($exitCode)->toBe(0)
        ->and(file_exists($file))->toBeFalse();
});

it('deletes nothing and reports zero when every file is within the retention window', function () {
    mkdir($this->storagePath, recursive: true);
    config(['homie.demo_db_storage_path' => $this->storagePath]);

    $file = "{$this->storagePath}/recent.sqlite";
    file_put_contents($file, 'data');

    $exitCode = Artisan::call('demo:cleanup');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Deleted 0 stale demo database file')
        ->and(file_exists($file))->toBeTrue();
});
