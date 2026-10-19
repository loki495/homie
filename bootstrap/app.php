<?php

declare(strict_types=1);

use App\Http\Middleware\AutoLoginForTrustedRequests;
use App\Http\Middleware\FrameAncestors;
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
        // accuracy of logged/reported client IPs. With '*', $request->ip() is
        // client-controllable, so never use it for a trust decision (auto-login
        // reads REMOTE_ADDR for that).
        $middleware->trustProxies(at: '*');

        $middleware->prependToGroup('web', UseStaticAssetsForRemoteHost::class);
        $middleware->prependToGroup('web', FrameAncestors::class);

        // Must run before StartSession, not just before RequireAuthentication:
        // the session handler holds the DB connection it resolved at
        // construction, and DB::purge('sqlite') cannot reach that reference, so
        // appended, the session is saved to the pre-repoint database and login
        // bounces back to /login. Livewire::test() skips the middleware
        // pipeline; tests/Browser/AuthenticationTest.php covers the ordering.
        $middleware->prependToGroup('web', ResolveDemoDatabase::class);

        // RequireAuthentication needs Auth::guard()->check(), which needs the
        // session already started - stays appended (after StartSession).
        // Auto-login (demo mode + opt-in only) must land before the auth gate below.
        $middleware->appendToGroup('web', AutoLoginForTrustedRequests::class);
        $middleware->appendToGroup('web', RequireAuthentication::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
