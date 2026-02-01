<?php

namespace App\Http\Middleware;

use Illuminate\View\Middleware\ShareErrorsFromSession;

class ShareErrorsFromSessionWithDebugBypass extends ShareErrorsFromSession
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
        return $request->is('_debug/ping-json') || $request->is('_debug/trace');
    }
}
