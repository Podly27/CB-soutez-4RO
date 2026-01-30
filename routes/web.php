<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

// Index page & related
$router->get('/health', function () {
    $safeValue = static function (callable $callback, $fallback) {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return $fallback;
        }
    };

    $dbConnectOk = false;
    $dbError = null;
    try {
        DB::connection()->getPdo();
        $dbConnectOk = true;
    } catch (\Throwable $e) {
        $dbError = preg_replace('/password\\s*=[^\\s]+/i', 'password=***', $e->getMessage());
    }

    $ownerMailRaw = $safeValue(static function () {
        return env('CTVERO_OWNER_MAIL')
            ?: env('CTVERO_OWNER_EMAIL')
            ?: env('OWNER_MAIL');
    }, null);
    $ownerMailHint = $safeValue(static function () use ($ownerMailRaw) {
        if (! is_string($ownerMailRaw) || $ownerMailRaw === '') {
            return '';
        }

        if (strpos($ownerMailRaw, '@') !== false) {
            [$local, $domain] = explode('@', $ownerMailRaw, 2);
            $maskedLocal = substr($local, 0, 3);
            if ($maskedLocal === false) {
                $maskedLocal = '';
            }
            return $maskedLocal . '***@' . $domain;
        }

        return substr($ownerMailRaw, 0, 3) . '***';
    }, '');

    return response()->json([
        'status' => 'ok',
        'php' => $safeValue(static function () {
            return phpversion();
        }, 'unknown'),
        'app_env' => $safeValue(static function () {
            return env('APP_ENV');
        }, 'unknown'),
        'app_key_present' => $safeValue(static function () {
            return (bool) env('APP_KEY');
        }, false),
        'vendor_present' => $safeValue(static function () {
            return file_exists(base_path('vendor/autoload.php'));
        }, false),
        'storage_dir' => $safeValue(static function () {
            return is_dir(base_path('storage'));
        }, false),
        'storage_writable' => $safeValue(static function () {
            return is_writable(base_path('storage'));
        }, false),
        'logs_dir' => $safeValue(static function () {
            return is_dir(base_path('storage/logs'));
        }, false),
        'logs_writable' => $safeValue(static function () {
            return is_writable(base_path('storage/logs'));
        }, false),
        'cache_dir' => $safeValue(static function () {
            return is_dir(base_path('bootstrap/cache'));
        }, false),
        'cache_writable' => $safeValue(static function () {
            return is_writable(base_path('bootstrap/cache'));
        }, false),
        'has_facebook_client_id' => $safeValue(static function () {
            return (bool) env('FACEBOOK_APP_ID');
        }, false),
        'has_facebook_client_secret' => $safeValue(static function () {
            return (bool) env('FACEBOOK_APP_SECRET');
        }, false),
        'facebook_redirect_uri' => $safeValue(static function () {
            return (string) env('FACEBOOK_REDIRECT_URI');
        }, ''),
        'owner_mail_set' => $safeValue(static function () use ($ownerMailRaw) {
            return is_string($ownerMailRaw) && $ownerMailRaw !== '';
        }, false),
        'owner_mail_hint' => $ownerMailHint,
        'db_connect_ok' => $dbConnectOk,
        'db_error' => $dbError,
    ], 200);
});

$router->get('/_debug/response', function () {
    return response()->json(['ok' => true], 200);
});

$router->get('/_debug/routes-auth', function () {
    $routeSource = app('router')->getRoutes();
    if ($routeSource instanceof \Illuminate\Routing\RouteCollection) {
        $routes = $routeSource->getRoutes();
    } elseif ($routeSource instanceof \Traversable) {
        $routes = iterator_to_array($routeSource);
    } else {
        $routes = is_array($routeSource) ? $routeSource : [];
    }

    $matches = [];
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

        $shouldInclude = Str::contains($uri, ['/auth', '/login', 'facebook', 'google', 'twitter']);

        if (! $shouldInclude) {
            continue;
        }

        if (is_string($methods)) {
            $methods = [$methods];
        }

        $matches[] = [
            'path' => $uri,
            'methods' => array_values($methods),
        ];
    }

    return response()->json($matches, 200);
});

$router->get('/diag', function () {
    $token = env('DIAG_TOKEN');
    $requestToken = request()->query('token');

    if (! $token || $requestToken !== $token) {
        abort(404);
    }

    $safeValue = static function (callable $callback, $fallback) {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return $fallback;
        }
    };

    $lastExceptionPath = storage_path('logs/last_exception.txt');
    $lastException = $safeValue(static function () use ($lastExceptionPath) {
        if (! file_exists($lastExceptionPath)) {
            return null;
        }
        if (! is_readable($lastExceptionPath)) {
            return false;
        }
        return file_get_contents($lastExceptionPath);
    }, false);

    $lastOauthErrorPath = storage_path('logs/oauth_last_error.txt');
    $lastOauthError = $safeValue(static function () use ($lastOauthErrorPath) {
        if (! file_exists($lastOauthErrorPath)) {
            return null;
        }
        if (! is_readable($lastOauthErrorPath)) {
            return false;
        }
        return file_get_contents($lastOauthErrorPath);
    }, false);

    if ($lastException === false) {
        return response()->json([
            'status' => 'no-log',
            'reason' => 'not readable/writable',
        ], 200);
    }

    $lastRejectedProviderPath = storage_path('logs/last_rejected_provider.txt');
    $lastRejectedProvider = $safeValue(static function () use ($lastRejectedProviderPath) {
        if (! file_exists($lastRejectedProviderPath)) {
            return null;
        }
        if (! is_readable($lastRejectedProviderPath)) {
            return false;
        }
        return file_get_contents($lastRejectedProviderPath);
    }, false);

    $lastMailErrorPath = storage_path('logs/last_mail_error.txt');
    $lastMailError = $safeValue(static function () use ($lastMailErrorPath) {
        if (! file_exists($lastMailErrorPath)) {
            return null;
        }
        if (! is_readable($lastMailErrorPath)) {
            return false;
        }
        return file_get_contents($lastMailErrorPath);
    }, false);

    return response()->json([
        'storage_framework_exists' => $safeValue(static function () {
            return is_dir(storage_path('framework'));
        }, false),
        'storage_writable' => $safeValue(static function () {
            return is_writable(storage_path());
        }, false),
        'bootstrap_cache_writable' => $safeValue(static function () {
            return is_writable(base_path('bootstrap/cache'));
        }, false),
        'app_key_set' => $safeValue(static function () {
            return strlen((string) config('app.key')) > 0;
        }, false),
        'last_exception' => $lastException,
        'oauth_last_error' => $lastOauthError === false ? null : $lastOauthError,
        'last_mail_error' => $lastMailError === false ? null : $lastMailError,
        'last_rejected_provider' => $lastRejectedProvider === false ? null : $lastRejectedProvider,
    ], 200);
});

$router->get('/_debug/mail', function () {
    $token = env('DIAG_TOKEN');
    $requestToken = request()->query('token');

    if (! $token || $requestToken !== $token) {
        abort(404);
    }

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
});

$router->get('/_setup/migrate', function () {
    if (! app()->environment('production')) {
        abort(404);
    }

    $token = env('SETUP_TOKEN');
    $requestToken = request()->query('token');

    if (! $token || $requestToken !== $token) {
        abort(404);
    }

    $markerPath = storage_path('app/setup_done');
    if (file_exists($markerPath)) {
        return response('Setup already completed.', 410, ['Content-Type' => 'text/plain']);
    }

    try {
        $migrationPath = function_exists('database_path')
            ? database_path('migrations')
            : base_path('database/migrations');

        if (! is_dir($migrationPath)) {
            return response("Migration path not found: {$migrationPath}", 500, ['Content-Type' => 'text/plain']);
        }

        $migrationFiles = glob($migrationPath . '/*.php');
        if ($migrationFiles === false || count($migrationFiles) === 0) {
            return response("No migration files found in: {$migrationPath}", 500, ['Content-Type' => 'text/plain']);
        }

        $resolver = app('db');
        $repository = new \Illuminate\Database\Migrations\DatabaseMigrationRepository(
            $resolver,
            'migrations'
        );
        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }
        $files = app('files');
        $migrator = new \Illuminate\Database\Migrations\Migrator($repository, $resolver, $files);
        $bufferedOutput = new \Symfony\Component\Console\Output\BufferedOutput();
        $migrator->setOutput($bufferedOutput);

        $migrator->run($migrationPath, ['pretend' => false, 'step' => true]);
        $output = $bufferedOutput->fetch();
        if ($output === '') {
            $output = "Migrations ran successfully.\n";
        }

        file_put_contents($markerPath, 'completed_at=' . date(DATE_ATOM));

        return response($output, 200, ['Content-Type' => 'text/plain']);
    } catch (\Throwable $e) {
        $message = sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine());
        try {
            file_put_contents(storage_path('logs/last_exception.txt'), $message);
        } catch (\Throwable $logException) {
            // Best effort logging only.
        }

        return response($message, 500, ['Content-Type' => 'text/plain']);
    }
});

$router->get('/', [
    'as' => 'index',
    'uses' => 'PageController@indexSafe',
]);
$router->get('/calendar', [
    'as' => 'calendar',
    'uses' => 'CalendarController@download'
]);
$router->get('/kalendar', function () {
    return redirect(route('calendar', [ 'contest' => request()->input('soutez') ]));
});
$router->get('/contact', [
    'as' => 'contact',
    'uses' => 'PageController@contact',
]);
$router->post('/message', [
    'as' => 'message',
    'uses' => 'MessageController@send'
]);

// Contest(s)
$router->get('/contest/{name}', [
    'as' => 'contest',
    'uses' => 'ContestController@show'
]);
$router->get('/contests', [
    'as' => 'contests',
    'uses' => 'ContestController@showAll'
]);

// Client localization
$router->get('/lang/{lang}', [
    'as' => 'lang',
    'uses' => 'PageController@setLocale',
]);

// Login & logout
$router->get('/auth/google', [
    'as' => 'authGoogle',
    'uses' => 'LoginController@loginGoogle',
]);
$router->get('/auth/google/callback', [
    'as' => 'authGoogleCallback',
    'uses' => 'LoginController@loginGoogleCallback',
]);
$router->get('/auth/facebook', [
    'as' => 'authFacebook',
    'uses' => 'LoginController@loginFacebook',
]);
$router->get('/auth/facebook/callback', [
    'as' => 'authFacebookCallback',
    'uses' => 'LoginController@loginFacebookCallback',
]);
$router->get('/auth/twitter', [
    'as' => 'authTwitter',
    'uses' => 'LoginController@loginTwitter',
]);
$router->get('/auth/twitter/callback', [
    'as' => 'authTwitterCallback',
    'uses' => 'LoginController@loginTwitterCallback',
]);
$router->get('/login/{provider}', [
    'as' => 'login',
    'uses' => 'LoginController@login'
]);
$router->get('/login/{provider}/callback', [
    'as' => 'loginCallback',
    'uses' => 'LoginController@callback'
]);
$router->get('/logout', [
    'as' => 'logout',
    'uses' => 'LoginController@logout'
]);

// Profile
$router->get('/profile', [
    'as' => 'profile',
    'uses' => 'PageController@profile',
]);

// Results
$router->get('/results', [
    'as' => 'results',
    'uses' => 'ResultsController@show'
]);
$router->get('/vysledky', function () {
    return redirect(route('results'));
});

// Submission
$router->get('/submission', [
    'as' => 'submissionForm',
    'uses' => 'SubmissionController@show'
]);
$router->post('/submission', [
    'as' => 'submission',
    'uses' => 'SubmissionController@submit'
]);
$router->get('/hlaseni', function () {
    return redirect(route('submissionForm'));
});

// Terms and privacy
$router->get('/terms-and-privacy', [
    'as' => 'termsAndPrivacy',
    'uses' => 'PageController@termsAndPrivacy',
]);

// APIv0
$router->addRoute([ 'GET', 'POST' ], '/api/v0/{category}/{endpoint}', [
    'as' => 'apiV0',
    'uses' => 'ApiV0Controller@route'
]);
