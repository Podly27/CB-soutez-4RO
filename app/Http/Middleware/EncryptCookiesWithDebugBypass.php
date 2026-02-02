<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies;

class EncryptCookiesWithDebugBypass extends EncryptCookies
{
    public function handle($request, \Closure $next)
    {
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    private function shouldBypass($request): bool
    {
        return $request->is('_debug/ping-json')
            || $request->is('_debug/trace')
            || $request->is('_debug/route-trace')
            || $request->is('public/_debug/ping-json')
            || $request->is('public/_debug/trace')
            || $request->is('public/_debug/route-trace');
    }
}
