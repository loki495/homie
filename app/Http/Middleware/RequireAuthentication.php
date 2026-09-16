<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every route except /login behind a real session login - including
 * demo mode, which uses the exact same login page against a shared admin
 * user seeded into the demo template itself (see BuildDemoTemplate), rather
 * than a separate mechanism. Demo mode used to gate access with HTTP Basic
 * Auth instead (RequireBasicAuthInDemoMode, since removed) - switched to
 * reusing this real login so the demo actually showcases the feature it's
 * meant to prove exists, and because the per-visitor SQLite copy
 * (ResolveDemoDatabase) already gives every visitor their own users table
 * with that same shared row in it, so no extra plumbing was needed to make
 * this work per-visitor.
 *
 * Must not redirect Livewire's own update requests (X-Livewire header) - that
 * endpoint shares the 'web' middleware group, so without this exemption the
 * login form's own submit request would get redirected to /login *before*
 * Auth::attempt() ever ran (found live: fields reset with no visible error,
 * because Livewire's JS received a 302/HTML response instead of its expected
 * JSON one). Safe to exempt unconditionally: Livewire signs every component
 * snapshot against APP_KEY, so an unauthenticated visitor can only ever hold
 * a valid snapshot for a component a public page actually served them (i.e.
 * the login form itself) - there's no protected-component snapshot to forge
 * without having loaded an authenticated page first.
 */
class RequireAuthentication
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('login', 'logout') || Livewire::isLivewireRequest()) {
            return $next($request);
        }

        if (! Auth::guard('web')->check()) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
