<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opt-in, off by default: signs an existing account in automatically for the deployment owner so they
 * skip the login page. Works on any deployment (demo, dev or real); it only ever signs in
 * config('homie.auto_login_email') - never creates an account. In demo mode that defaults to the shared
 * demo account.
 *
 * Never uses $request->ip(): trustProxies('*') makes it the client-supplied X-Forwarded-For entry, so the check reads the socket peer (REMOTE_ADDR) instead. A request counts
 * as LAN when auto_login_lan is on, it carries no Cloudflare edge header (CF-Connecting-IP/CF-Ray, which
 * only Cloudflare adds) and its peer is a private address - only valid when nothing but the tunnel and the
 * LAN can reach this app. A request that did come through Cloudflare is trusted only when Cloudflare Access
 * itself asserted auto_login_owner_email (Cf-Access-Authenticated-User-Email).
 * Must run after StartSession (and ResolveDemoDatabase in demo mode) and before the auth gate.
 */
class AutoLoginForTrustedRequests
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $email = $this->accountEmail();

        if ($email !== null && ! Auth::check() && $this->isTrusted($request)) {
            $user = User::query()->where('email', $email)->first();

            if ($user !== null) {
                Auth::login($user);
            }
        }

        return $next($request);
    }

    private function accountEmail(): ?string
    {
        $email = config('homie.auto_login_email') ?: (config('homie.demo_mode') ? config('homie.demo_admin_email') : null);

        return is_string($email) && $email !== '' ? $email : null;
    }

    private function isTrusted(Request $request): bool
    {
        if (! $request->headers->has('CF-Connecting-IP') && ! $request->headers->has('CF-Ray')) {
            $ip = $request->server->get('REMOTE_ADDR');

            return (bool) config('homie.auto_login_lan')
                && is_string($ip)
                && filter_var($ip, FILTER_VALIDATE_IP) !== false
                && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false;
        }

        $ownerEmail = config('homie.auto_login_owner_email');

        return $ownerEmail !== null && $ownerEmail !== ''
            && $request->header('Cf-Access-Authenticated-User-Email') === $ownerEmail;
    }
}
