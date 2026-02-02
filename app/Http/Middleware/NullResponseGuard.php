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
            $msg = "NULL RESPONSE\n"
                ."uri: ".($_SERVER['REQUEST_URI'] ?? '')."\n"
                ."route: ".($request->path() ?? '')."\n"
                ."method: ".$request->method()."\n";
            @file_put_contents(storage_path('logs/last_exception.txt'), $msg);

            return response('NULL response produced by downstream handler', 500);
        }

        return $response;
    }
}
