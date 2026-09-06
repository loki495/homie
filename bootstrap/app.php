<?php

declare(strict_types=1);

use App\Http\Middleware\RequireBasicAuthInDemoMode;
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

        // Demo mode only (config('homie.demo_mode')) - no-op otherwise. Order
        // matters: the database must be resolved to the current visitor's own
        // copy before the Basic Auth check queries the users table.
        $middleware->appendToGroup('web', [
            ResolveDemoDatabase::class,
            RequireBasicAuthInDemoMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
