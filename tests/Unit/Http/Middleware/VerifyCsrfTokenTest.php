<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;

/**
 * Laravel's own CSRF middleware always no-ops during unit tests
 * (PreventRequestForgery::runningUnitTests()), so a real HTTP round trip
 * can't exercise our LAN-bypass logic. Call tokensMatch() directly instead.
 */
function callTokensMatch(Request $request): bool
{
    $middleware = new VerifyCsrfToken(app(), app('encrypter'));

    $method = new ReflectionMethod($middleware, 'tokensMatch');

    return $method->invoke($middleware, $request);
}

it('bypasses the CSRF token check for a request from a private IP', function () {
    $request = Request::create('/', 'POST');
    $request->server->set('REMOTE_ADDR', '192.168.1.50');
    $request->setLaravelSession(app('session.store'));

    expect(callTokensMatch($request))->toBeTrue();
});

it('bypasses the CSRF token check for a request from a reserved (loopback) IP', function () {
    $request = Request::create('/', 'POST');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');
    $request->setLaravelSession(app('session.store'));

    expect(callTokensMatch($request))->toBeTrue();
});

it('still requires a matching token for a request from a public IP', function () {
    $request = Request::create('/', 'POST');
    $request->server->set('REMOTE_ADDR', '203.0.113.5');
    $request->setLaravelSession(app('session.store'));

    expect(callTokensMatch($request))->toBeFalse();
});

it('accepts a public-IP request whose token matches the session token', function () {
    $session = app('session.store');
    $session->start();

    $request = Request::create('/', 'POST', ['_token' => $session->token()]);
    $request->server->set('REMOTE_ADDR', '203.0.113.5');
    $request->setLaravelSession($session);

    expect(callTokensMatch($request))->toBeTrue();
});

it('rejects a public-IP request whose token does not match the session token', function () {
    $session = app('session.store');

    $request = Request::create('/', 'POST', ['_token' => 'wrong-token']);
    $request->server->set('REMOTE_ADDR', '203.0.113.5');
    $request->setLaravelSession($session);

    expect(callTokensMatch($request))->toBeFalse();
});
