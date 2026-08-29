<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class UseStaticAssetsForRemoteHost
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hotFile = Vite::hotFile();
        $secureCookie = config('session.secure');

        if (in_array($request->getHost(), config('app.static_asset_hosts', []), true)) {
            Vite::useHotFile(public_path('hot-static'));
            config()->set('session.secure', true);
        }

        try {
            return $next($request);
        } finally {
            Vite::useHotFile($hotFile);
            config()->set('session.secure', $secureCookie);
        }
    }
}
