<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;

class VerifyCsrfToken extends Middleware
{
    #[\Override]
    protected function tokensMatch($request): bool
    {
        return $this->isLanRequest($request) || parent::tokensMatch($request);
    }

    private function isLanRequest(Request $request): bool
    {
        if ($request->headers->has('CF-Connecting-IP')) {
            return false;
        }

        return filter_var(
            $request->ip(),
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
