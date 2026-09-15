<?php

declare(strict_types=1);

use App\Models\User;
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
