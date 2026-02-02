<?php

namespace App\Http\Middleware;

use Illuminate\Session\Middleware\StartSession;

class StartSessionWithDebugBypass extends StartSession
{
    public function handle($request, \Closure $next)
    {
        // pokud existuje bypass logika, zachovej ji,
        // ale vždy vrať Response
        if (method_exists($this, 'shouldBypass') && $this->shouldBypass($request)) {
            $resp = $next($request);

            if ($resp === null) {
                $resp = response(
                    'Downstream handler returned null (session bypass).',
                    500
                );
            }

            return $resp;
        }

        $resp = parent::handle($request, $next);

        if ($resp === null) {
            $resp = response(
                'Parent session middleware returned null.',
                500
            );
        }

        return $resp;
    }

    private function shouldBypass($request): bool
    {
        return $request->is('_debug/ping-json')
            || $request->is('_debug/trace')
            || $request->is('public/_debug/ping-json')
            || $request->is('public/_debug/trace');
    }
}
