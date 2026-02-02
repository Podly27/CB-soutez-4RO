<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;

class AddQueuedCookiesToResponseWithDebugBypass extends AddQueuedCookiesToResponse
{
    public function handle($request, \Closure $next)
    {
        $safeNext = function ($req) use ($next) {
            $resp = $next($req);

            if ($resp === null) {
                return response(
                    'Downstream handler returned null (wrapped by AddQueuedCookiesToResponseWithDebugBypass).',
                    500
                );
            }

            return $resp;
        };

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
