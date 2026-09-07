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
 *
 * Two opt-in bypasses for this deployment's own owner - see isTrustedRequest().
 * Both default off (demo_trust_lan / demo_owner_email both unset), so a normal
 * clone of this repo with demo mode on behaves exactly as before.
 */
class RequireBasicAuthInDemoMode
{
    public function __construct(private readonly AuthFactory $auth) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('homie.demo_mode') && ! $this->isTrustedRequest($request)) {
            $this->auth->guard()->basic('email');
        }

        return $next($request);
    }

    /**
     * Deliberately does NOT use $request->ip()/X-Forwarded-For - this repo's
     * own history (see CLAUDE.md, "Embedding in an iframe, and why there is
     * no CSRF exemption") already hit a real vulnerability from trusting a
     * client-appendable header for exactly this kind of "are they local"
     * check. Both branches below rely only on signals a client cannot inject
     * themselves:
     *
     * - LAN: Cloudflare-only headers (CF-Connecting-IP/CF-Ray) are added at
     *   Cloudflare's edge, never by a client. Their absence means this
     *   request reached the app without passing through Cloudflare at all -
     *   only possible if it came in over the LAN, since nothing else can
     *   reach the app directly on this deployment (no public port-forward -
     *   a fact about this one deployment's network, not something this repo
     *   can verify on its own). Also requires $request->ip() to actually be a
     *   private-range address - belt-and-suspenders on top of the
     *   Cloudflare-header check above, which is already sufficient on its
     *   own: for a request that never touched Cloudflare, this app's own
     *   reverse proxy is the first hop that ever sees it, so nothing upstream
     *   could have appended a spoofed entry ahead of the proxy's own honest
     *   one - unlike the exact scenario the CSRF fix above was about. Gated
     *   behind demo_trust_lan (default off) precisely because the "nothing
     *   but the LAN and Cloudflare can reach this app" assumption is
     *   deployment-specific, not something a random clone of this repo
     *   should inherit for free.
     * - Owner recognized through Cloudflare: Cf-Access-Authenticated-User-
     *   Email is set by Cloudflare Access itself once it has verified a real
     *   login: Cloudflare strips any client-supplied copy of this header at
     *   the edge before adding its own, so a request that goes through
     *   Cloudflare cannot forge it. Currently inert unless this hostname's
     *   Cloudflare Access policy is configured to still assert identity for
     *   a logged-in owner while remaining open to everyone else - it is a
     *   plain public bypass today (see this deployment's own Cloudflare
     *   config, not tracked in this repo).
     */
    private function isTrustedRequest(Request $request): bool
    {
        $wentThroughCloudflare = $request->headers->has('CF-Connecting-IP') || $request->headers->has('CF-Ray');

        if (! $wentThroughCloudflare) {
            return (bool) config('homie.demo_trust_lan') && $this->isPrivateIp($request->ip());
        }

        $ownerEmail = config('homie.demo_owner_email');

        return $ownerEmail !== null && $ownerEmail !== ''
            && $request->header('Cf-Access-Authenticated-User-Email') === $ownerEmail;
    }

    private function isPrivateIp(?string $ip): bool
    {
        return $ip !== null
            && filter_var($ip, FILTER_VALIDATE_IP) !== false
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false;
    }
}
