<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FrameAncestors
{
    private const string DEFAULT = "'self'";

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Content-Security-Policy', 'frame-ancestors '.$this->sources());

        return $response;
    }

    /**
     * Each token must be a quoted keyword or a plain origin; anything else
     * (semicolons or commas that would smuggle in other directives, control
     * characters) discards the whole value rather than sending a half-trusted one.
     */
    private function sources(): string
    {
        $configured = config('homie.frame_ancestors');
        $tokens = is_string($configured) ? preg_split('/\s+/', trim($configured), -1, PREG_SPLIT_NO_EMPTY) : [];

        if ($tokens === [] || $tokens === false) {
            return self::DEFAULT;
        }

        foreach ($tokens as $token) {
            if (! preg_match("/^(?:'(?:self|none)'|[a-z][a-z0-9+.-]*:(?:\/\/[a-z0-9*.\-]+(?::(?:\d+|\*))?)?)$/i", $token)) {
                return self::DEFAULT;
            }
        }

        return implode(' ', $tokens);
    }
}
