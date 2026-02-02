<?php

namespace App\Http\Middleware;

use Illuminate\Session\Middleware\StartSession;

class StartSessionWithDebugBypass extends StartSession
{
    public function handle($request, \Closure $next)
    {
        $safeNext = function ($req) use ($next) {
            $resp = $next($req);

            if ($resp === null) {
                return response(
                    'Downstream handler returned null (wrapped by StartSessionWithDebugBypass).',
                    500
                );
            }

            return $resp;
        };

        // bypass logika, pokud existuje – ale také používej safeNext
        if (method_exists($this, 'shouldBypass') && $this->shouldBypass($request)) {
            return $safeNext($request);
        }

        return parent::handle($request, $safeNext);
    }

    private function shouldBypass($request): bool
    {
        return $request->is('_debug/ping-json')
            || $request->is('_debug/trace')
            || $request->is('public/_debug/ping-json')
            || $request->is('public/_debug/trace');
    }
}
