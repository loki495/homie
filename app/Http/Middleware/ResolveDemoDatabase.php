<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo mode only (config('homie.demo_mode')): gives each visitor their own
 * private copy of the demo dataset instead of one database shared by every
 * concurrent visitor. Homie has no per-user data model at all, so this is the
 * only isolation mechanism available - see .ai/plans/2026-09-06-demo-sites-and-cd
 * (outside this repo) for the full design.
 */
class ResolveDemoDatabase
{
    private const string COOKIE_NAME = 'demo_instance_id';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('homie.demo_mode')) {
            return $next($request);
        }

        $demoId = $request->cookie(self::COOKIE_NAME);

        if (! is_string($demoId) || ! preg_match('/^[a-zA-Z0-9]{40}$/', $demoId)) {
            $demoId = Str::random(40);
            Cookie::queue(self::COOKIE_NAME, $demoId, 60 * 24 * 30);
        }

        $dbPath = rtrim((string) config('homie.demo_db_storage_path'), '/')."/{$demoId}.sqlite";

        if (! file_exists($dbPath)) {
            $template = config('homie.demo_db_template_path');

            if (! is_string($template) || ! file_exists($template)) {
                abort(500, 'Demo database template is missing.');
            }

            if (! is_dir(dirname($dbPath))) {
                mkdir(dirname($dbPath), recursive: true);
            }

            copy($template, $dbPath);
        }

        config(['database.connections.sqlite.database' => $dbPath]);

        // Force a reconnect: the 'sqlite' connection may already have been
        // resolved (e.g. by an earlier request in a long-running worker/
        // Octane process, or by the testing framework's own database
        // handling) - a bare config() change is silently ineffective for an
        // already-resolved connection instance, so every subsequent query
        // would keep hitting whatever database it originally connected to.
        DB::purge('sqlite');

        return $next($request);
    }
}
