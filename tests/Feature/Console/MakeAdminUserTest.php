<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

it('creates a new admin user from options', function () {
    $exitCode = Artisan::call('homie:make-admin', [
        '--email' => 'admin@example.com',
        '--password' => 'a-strong-password',
        '--name' => 'Andres',
    ]);

    expect($exitCode)->toBe(0);

    $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

    expect($user->name)->toBe('Andres')
        ->and(Hash::check('a-strong-password', $user->password))->toBeTrue();
});

it('resets the password of an existing admin instead of duplicating the row', function () {
    $existing = User::factory()->create(['email' => 'admin@example.com']);

    Artisan::call('homie:make-admin', [
        '--email' => 'admin@example.com',
        '--password' => 'a-new-password',
    ]);

    expect(User::query()->count())->toBe(1);

    $existing->refresh();

    expect(Hash::check('a-new-password', $existing->password))->toBeTrue();
});

it('fails validation for an invalid email without creating a user', function () {
    $exitCode = Artisan::call('homie:make-admin', [
        '--email' => 'not-an-email',
        '--password' => 'a-strong-password',
    ]);

    expect($exitCode)->toBe(1)
        ->and(User::query()->count())->toBe(0);
});

it('fails validation for a too-short password without creating a user', function () {
    $exitCode = Artisan::call('homie:make-admin', [
        '--email' => 'admin@example.com',
        '--password' => 'short',
    ]);

    expect($exitCode)->toBe(1)
        ->and(User::query()->count())->toBe(0);
});

it('provisions the shared demo credentials non-interactively when called bare in demo mode', function () {
    config([
        'homie.demo_mode' => true,
        'homie.demo_admin_email' => 'demo@example.com',
        'homie.demo_admin_password' => 'demo-password',
    ]);

    $exitCode = Artisan::call('homie:make-admin');

    expect($exitCode)->toBe(0);

    $user = User::query()->where('email', 'demo@example.com')->firstOrFail();

    expect($user->name)->toBe('Demo')
        ->and(Hash::check('demo-password', $user->password))->toBeTrue();
});

it('still lets an explicit --email/--password override the demo shortcut in demo mode', function () {
    config([
        'homie.demo_mode' => true,
        'homie.demo_admin_email' => 'demo@example.com',
        'homie.demo_admin_password' => 'demo-password',
    ]);

    Artisan::call('homie:make-admin', [
        '--email' => 'custom@example.com',
        '--password' => 'a-custom-password',
    ]);

    expect(User::query()->where('email', 'demo@example.com')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'custom@example.com')->exists())->toBeTrue();
});
