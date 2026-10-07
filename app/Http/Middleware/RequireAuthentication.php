<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
 * Livewire's update endpoint is exempt (by route, never by the spoofable
 * X-Livewire header) because it shares the 'web' group and the login form's
 * own submit would otherwise be redirected before Auth::attempt() ran. It is
 * safe because this middleware is registered as Livewire persistent
 * middleware (AppServiceProvider), so every update re-runs it against the
 * route the component was loaded from: a guest can only update components
 * served on public routes (login).
 */
class RequireAuthentication
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('login', 'logout', '*livewire.update')) {
            return $next($request);
        }

        if (! Auth::guard('web')->check()) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
