<?php

declare(strict_types=1);

use App\Models\User;
use Tests\TestCase;

/**
 * The opt-in auto-login on a normal (non-demo) deployment: it only ever signs in an existing account named
 * by auto_login_email, and only for a trusted request.
 */
beforeEach(function (): void {
    /** @var TestCase $this */
    $this->withoutVite();
    User::factory()->create(['email' => 'owner@example.com']);
});

it('does nothing by default', function (): void {
    /** @var TestCase $this */
    $this->call('GET', '/', server: ['REMOTE_ADDR' => '192.168.1.50'])->assertRedirect('/login');
});

it('signs in the configured account for a LAN request when opted in', function (): void {
    /** @var TestCase $this */
    config(['homie.auto_login_lan' => true, 'homie.auto_login_email' => 'owner@example.com']);

    $this->call('GET', '/', server: ['REMOTE_ADDR' => '192.168.1.50'])->assertOk();

    $this->assertAuthenticated();
    expect(auth()->user()?->email)->toBe('owner@example.com');
});

it('does not sign in without an account configured, or for a missing account', function (): void {
    /** @var TestCase $this */
    config(['homie.auto_login_lan' => true]);
    $this->call('GET', '/', server: ['REMOTE_ADDR' => '192.168.1.50'])->assertRedirect('/login');

    config(['homie.auto_login_email' => 'nobody@example.com']);
    $this->call('GET', '/', server: ['REMOTE_ADDR' => '192.168.1.50'])->assertRedirect('/login');

    $this->assertGuest();
});

it('does not sign in a public address or a Cloudflare-routed request', function (): void {
    /** @var TestCase $this */
    config(['homie.auto_login_lan' => true, 'homie.auto_login_email' => 'owner@example.com']);

    $this->call('GET', '/', server: ['REMOTE_ADDR' => '8.8.8.8'])->assertRedirect('/login');
    $this->call('GET', '/', server: ['REMOTE_ADDR' => '192.168.1.50', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4'])
        ->assertRedirect('/login');
});

it('signs in when Cloudflare Access asserts the owner email, not on a mismatch', function (): void {
    /** @var TestCase $this */
    config(['homie.auto_login_email' => 'owner@example.com', 'homie.auto_login_owner_email' => 'me@example.com']);

    $this->withHeaders(['CF-Connecting-IP' => '1.2.3.4', 'Cf-Access-Authenticated-User-Email' => 'other@example.com'])
        ->get('/')->assertRedirect('/login');

    $this->withHeaders(['CF-Connecting-IP' => '1.2.3.4', 'Cf-Access-Authenticated-User-Email' => 'me@example.com'])
        ->get('/')->assertOk();
    $this->assertAuthenticated();
});

it('ignores a spoofed X-Forwarded-For when judging whether a request is on the LAN', function (): void {
    /** @var TestCase $this */
    config(['homie.auto_login_lan' => true, 'homie.auto_login_email' => 'owner@example.com']);

    $this->call('GET', '/', server: ['REMOTE_ADDR' => '8.8.8.8', 'HTTP_X_FORWARDED_FOR' => '192.168.1.50'])
        ->assertRedirect('/login');
    $this->assertGuest();
});

it('still signs in a private peer whose forwarded client address is public', function (): void {
    /** @var TestCase $this */
    config(['homie.auto_login_lan' => true, 'homie.auto_login_email' => 'owner@example.com']);

    $this->call('GET', '/', server: ['REMOTE_ADDR' => '172.18.0.2', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8'])->assertOk();
    $this->assertAuthenticated();
});

it('keeps Livewire updates working for an auto-logged-in LAN request', function (): void {
    /** @var TestCase $this */
    config(['homie.auto_login_lan' => true, 'homie.auto_login_email' => 'owner@example.com']);
    $server = ['REMOTE_ADDR' => '192.168.1.50'];

    $html = (string) $this->call('GET', '/', server: $server)->assertOk()->getContent();
    preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
    expect($m)->not->toBeEmpty();
    auth()->logout();
    app('auth')->forgetGuards();

    $this->call('POST', route('default-livewire.update'), server: $server + ['HTTP_X_LIVEWIRE' => '1', 'CONTENT_TYPE' => 'application/json'], content: json_encode([
        'components' => [['snapshot' => html_entity_decode($m[1]), 'updates' => [], 'calls' => []]],
    ]))->assertOk();
});
