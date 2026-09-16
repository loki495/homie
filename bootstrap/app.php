<?php

declare(strict_types=1);

use App\Http\Middleware\RequireAuthentication;
use App\Http\Middleware\ResolveDemoDatabase;
use App\Http\Middleware\UseStaticAssetsForRemoteHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the Traefik reverse proxy so $request->ip() resolves the real
        // client IP from X-Forwarded-For instead of Traefik's own docker-network IP.
        // No longer load-bearing for security: CSRF used to be waived for
        // private-range IPs, which made a spoofable header the only thing
        // standing between a request and an unprotected POST. That bypass is
        // gone (see SESSION_SAME_SITE in .env.example) and Laravel's normal
        // token check now runs on every request, so this only affects the
        // accuracy of logged/reported client IPs.
        $middleware->trustProxies(at: '*');

        $middleware->prependToGroup('web', UseStaticAssetsForRemoteHost::class);

        // ResolveDemoDatabase must run before Laravel's own StartSession -
        // not just before RequireAuthentication's users-table query. Found
        // live: appending it (running after StartSession) let the session
        // handler resolve and cache its own DB connection reference against
        // whatever 'sqlite' pointed at *before* the per-visitor repoint;
        // DB::purge('sqlite') later in the same request doesn't reach that
        // already-grabbed reference, so the session got saved against the
        // wrong database entirely - login "succeeded" in-request (Auth::
        // attempt() and everything after it used the correctly-repointed
        // connection) but the persisted session never carried the auth state
        // to the next request, bouncing straight back to /login. Prepending
        // puts it ahead of EncryptCookies/StartSession/etc. in the 'web'
        // group, so the session handler is constructed against the correct
        // connection from the start - no purge-timing race at all. Caught
        // live in a real browser, not by the test suite: Livewire::test()
        // bypasses the HTTP middleware pipeline entirely, so it can't
        // reproduce an ordering bug between two middlewares - see
        // tests/Browser/AuthenticationTest.php for the browser-level test
        // this added to actually catch it.
        $middleware->prependToGroup('web', ResolveDemoDatabase::class);

        // RequireAuthentication needs Auth::guard()->check(), which needs the
        // session already started - stays appended (after StartSession).
        $middleware->appendToGroup('web', RequireAuthentication::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
