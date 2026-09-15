<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every route except /login behind a real session login, unless demo
 * mode is on - demo mode already gates the whole app behind
 * RequireBasicAuthInDemoMode instead (a single shared Basic Auth user, since
 * per-visitor session login would need its own onboarding flow demo mode has
 * no use for). Checks config('homie.demo_mode') per-request, same convention
 * as ResolveDemoDatabase/RequireBasicAuthInDemoMode, so the same test suite
 * pattern of toggling config() at runtime works here too - a route-
 * registration-time branch in routes/web.php would bake in whatever
 * demo_mode was at boot and never react to a test (or a real deployment)
 * flipping it later in the same process.
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
class RequireAuthenticationUnlessDemoMode
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('homie.demo_mode')) {
            return $next($request);
        }

        if ($request->routeIs('login', 'logout') || Livewire::isLivewireRequest()) {
            return $next($request);
        }

        if (! Auth::guard('web')->check()) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
