<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

it('redirects a guest hitting the dashboard to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('lets an authenticated user reach the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')->assertOk();
});

it('redirects an already-authenticated user away from the login page', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/login')->assertRedirect('/');
});

it('logs a user in with valid credentials and redirects to the dashboard', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-password')]);

    Livewire::test('login')
        ->set('email', $user->email)
        ->set('password', 'correct-password')
        ->call('login')
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

it('rejects an invalid password without logging in', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-password')]);

    Livewire::test('login')
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

it('rejects an unknown email without leaking whether it exists', function () {
    Livewire::test('login')
        ->set('email', 'nobody@example.com')
        ->set('password', 'whatever')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

it('requires an email and a password', function () {
    Livewire::test('login')
        ->set('email', '')
        ->set('password', '')
        ->call('login')
        ->assertHasErrors(['email', 'password']);
});

it('locks out repeated failed attempts for the same email/IP', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-password')]);

    RateLimiter::clear(mb_strtolower($user->email).'|127.0.0.1');

    for ($i = 0; $i < 5; $i++) {
        Livewire::test('login')
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login');
    }

    Livewire::test('login')
        ->set('email', $user->email)
        ->set('password', 'correct-password')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

it('logs an authenticated user out', function () {
    $this->actingAs($user = User::factory()->create());

    $this->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
});

it('does not redirect to the login page when demo mode is on', function () {
    config(['homie.demo_mode' => true]);

    // Demo mode is gated by RequireBasicAuthInDemoMode instead, which is
    // exercised by DemoModeTest.php — this only asserts the new auth
    // middleware itself doesn't redirect to /login while demo mode is on.
    $response = $this->get('/');

    expect($response->getStatusCode())->not->toBe(302);
});
