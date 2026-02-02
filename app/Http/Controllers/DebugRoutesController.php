<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DebugRoutesController extends Controller
{
    public function response()
    {
        $this->ensureDiagToken();

        return response()->json(['ok' => true], 200);
    }

    public function pingJson()
    {
        return response()->json([
            'ok' => true,
            'ts' => date('c'),
        ], 200);
    }

    public function echoQs()
    {
        $this->ensureDiagToken();

        return response()->json([
            'query' => request()->query(),
        ], 200);
    }

    public function routeTrace()
    {
        $this->ensureDiagToken();

        $tracePath = storage_path('logs/route_trace.txt');
        $relativePath = 'storage/logs/route_trace.txt';
        $exists = file_exists($tracePath);
        $tail = '';

        if ($exists && is_readable($tracePath)) {
            $lines = @file($tracePath, FILE_IGNORE_NEW_LINES);
            if ($lines !== false) {
                $tailLines = array_slice($lines, -50);
                $tail = implode("\n", $tailLines);
            }
        }

        return response()->json([
            'ok' => true,
            'file' => $relativePath,
            'exists' => $exists,
            'tail' => $tail,
        ], 200);
    }

    public function routes()
    {
        $this->ensureDiagToken();

        $payload = $this->buildRoutesPayload();

        return response()->json($payload, 200);
    }

    public function routesAuth()
    {
        $this->ensureDiagToken();

        $routes = $this->buildRoutesPayload();
        $matches = [];

        foreach ($routes as $route) {
            $path = $route['path'] ?? '';
            if ($path === '') {
                continue;
            }

            $shouldInclude = Str::contains($path, ['/auth', '/login', 'facebook', 'google', 'twitter']);
            if (! $shouldInclude) {
                continue;
            }

            $matches[] = $route;
        }

        return response()->json($matches, 200);
    }

    public function dbSchema()
    {
        $this->ensureDiagToken();

        try {
            $columns = DB::select("SHOW COLUMNS FROM `user` LIKE 'email'");
        } catch (\Throwable $e) {
            $message = sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine());

            return response()->json([
                'error' => $message,
            ], 500);
        }

        return response()->json($columns, 200);
    }

    public function mail()
    {
        $this->ensureDiagToken();

        $defaultMailer = config('mail.default');
        $mailerConfig = config("mail.mailers.{$defaultMailer}", []);

        return response()->json([
            'mailer' => $defaultMailer,
            'host' => $mailerConfig['host'] ?? null,
            'port' => $mailerConfig['port'] ?? null,
            'encryption' => $mailerConfig['encryption'] ?? null,
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ], 200);
    }

    private function ensureDiagToken()
    {
        $token = env('DIAG_TOKEN');
        $requestToken = request()->query('token');

        if (! $token || ! hash_equals((string) $token, (string) $requestToken)) {
            abort(404);
        }
    }

    private function buildRoutesPayload()
    {
        $routeSource = app('router')->getRoutes();
        if ($routeSource instanceof \Illuminate\Routing\RouteCollection) {
            $routes = $routeSource->getRoutes();
        } elseif ($routeSource instanceof \Traversable) {
            $routes = iterator_to_array($routeSource);
        } else {
            $routes = is_array($routeSource) ? $routeSource : [];
        }

        $payload = [];
        foreach ($routes as $route) {
            $uri = null;
            $methods = [];

            if ($route instanceof \Illuminate\Routing\Route) {
                $uri = $route->uri();
                $methods = $route->methods();
            } elseif (is_array($route)) {
                $uri = $route['uri'] ?? $route['path'] ?? null;
                $methods = $route['methods'] ?? $route['method'] ?? [];
            }

            if (! $uri) {
                continue;
            }

            if (is_string($methods)) {
                $methods = [$methods];
            }

            $payload[] = [
                'path' => $uri,
                'methods' => array_values($methods),
            ];
        }

        return $payload;
    }
}
