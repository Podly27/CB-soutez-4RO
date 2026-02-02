<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;

class AddQueuedCookiesToResponseWithDebugBypass extends AddQueuedCookiesToResponse
{
    public function handle($request, \Closure $next)
    {
        if ($this->shouldBypass($request)) {
            $response = $next($request);
            if ($response === null) {
                return response('Middleware returned null', 500);
            }

            return $response;
        }

        return parent::handle($request, $next);
    }

    private function shouldBypass($request): bool
    {
        return $request->is('_debug/*');
    }
}
