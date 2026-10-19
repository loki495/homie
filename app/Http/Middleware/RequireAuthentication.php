<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a real session login for every page. Login, logout and the
 * Livewire update endpoint are exempt, and so is the /up health route, which
 * is registered outside the web group. Demo mode uses the exact same login
 * page against a shared admin user seeded into the demo template (see
 * BuildDemoTemplate); each visitor's own database copy (ResolveDemoDatabase)
 * already holds that row.
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
