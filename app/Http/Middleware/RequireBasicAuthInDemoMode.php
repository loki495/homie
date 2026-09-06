<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo mode only (config('homie.demo_mode')): gates the whole app behind HTTP
 * Basic Auth, since homie ships with no login of its own. Mirrors Laravel's own
 * built-in `auth.basic` route middleware (Illuminate\Auth\Middleware\
 * AuthenticateWithBasicAuth) exactly, just wrapped in the demo-mode check so it
 * has zero effect on a normal (non-demo) run of this same image. The one demo
 * user this checks against is seeded directly into the demo database template
 * (see ResolveDemoDatabase) - every visitor's copy already has it.
 */
class RequireBasicAuthInDemoMode
{
    public function __construct(private readonly AuthFactory $auth) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('homie.demo_mode')) {
            $this->auth->guard()->basic('email');
        }

        return $next($request);
    }
}
