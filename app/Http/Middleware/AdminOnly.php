<?php

namespace App\Http\Middleware;

use App\Http\Utilities;
use Closure;
use Illuminate\Support\Facades\Auth;

class AdminOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (! Auth::check() || ! Auth::user()) {
            return response('Forbidden.', 403);
        }

        $email = Auth::user()->email ?? null;
        if (! Utilities::isAdmin($email)) {
            return response('Forbidden.', 403);
        }

        return $next($request);
    }
}
