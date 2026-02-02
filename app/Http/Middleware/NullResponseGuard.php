<?php

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\Response;

class NullResponseGuard
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($response === null) {
            $uriWithQuery = $request->getRequestUri();
            $msg = "NULL RESPONSE\n"
                ."uri: ".($uriWithQuery ?? '')."\n"
                ."method: ".$request->method()."\n";
            @file_put_contents(storage_path('logs/last_exception.txt'), $msg);

            return response('NULL response produced by downstream handler', 500);
        }

        return $response;
    }
}
