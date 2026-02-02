<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;

class AddQueuedCookiesToResponseWithDebugBypass extends AddQueuedCookiesToResponse
{
    public function handle($request, \Closure $next)
    {
        if (method_exists($this, 'shouldBypass') && $this->shouldBypass($request)) {
            $resp = $next($request);

            if ($resp === null) {
                $resp = response(
                    'Downstream handler returned null (cookie bypass).',
                    500
                );
            }

            return $resp;
        }

        $resp = parent::handle($request, $next);

        if ($resp === null) {
            $resp = response(
                'Parent cookie middleware returned null.',
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
