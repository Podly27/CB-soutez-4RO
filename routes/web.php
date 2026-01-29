<?php

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\PageController;

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
        'db_connect_ok' => $dbConnectOk,
        'db_error' => $dbError,
    ], 200);
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

    if ($lastException === false) {
        return response()->json([
            'status' => 'no-log',
            'reason' => 'not readable/writable',
        ], 200);
    }

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

        $db = app('db');
        $repository = new \Illuminate\Database\Migrations\DatabaseMigrationRepository(
            $db->getSchemaBuilder()->getConnection(),
            'migrations'
        );
        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }
        $files = app('files');
        $migrator = new \Illuminate\Database\Migrations\Migrator($repository, $db, $files);
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
    'uses' => PageController::class . '@indexSafe',
]);
$router->get('/calendar', [
    'as' => 'calendar',
    'uses' => '\App\Http\Controllers\CalendarController@download'
]);
$router->get('/kalendar', function () {
    return redirect(route('calendar', [ 'contest' => request()->input('soutez') ]));
});
$router->get('/contact', [
    'as' => 'contact',
    'uses' => PageController::class . '@contact',
]);
$router->post('/message', [
    'as' => 'message',
    'uses' => '\App\Http\Controllers\MessageController@send'
]);

// Contest(s)
$router->get('/contest/{name}', [
    'as' => 'contest',
    'uses' => '\App\Http\Controllers\ContestController@show'
]);
$router->get('/contests', [
    'as' => 'contests',
    'uses' => '\App\Http\Controllers\ContestController@showAll'
]);

// Client localization
$router->get('/lang/{lang}', [
    'as' => 'lang',
    'uses' => PageController::class . '@setLocale',
]);

// Login & logout
$router->get('/login/{provider}', [
    'as' => 'login',
    'uses' => '\App\Http\Controllers\LoginController@login'
]);
$router->get('/login/{provider}/callback', [
    'as' => 'loginCallback',
    'uses' => '\App\Http\Controllers\LoginController@callback'
]);
$router->get('/logout', [
    'as' => 'logout',
    'uses' => '\App\Http\Controllers\LoginController@logout'
]);

// Profile
$router->get('/profile', [
    'as' => 'profile',
    'uses' => PageController::class . '@profile',
]);

// Results
$router->get('/results', [
    'as' => 'results',
    'uses' => '\App\Http\Controllers\ResultsController@show'
]);
$router->get('/vysledky', function () {
    return redirect(route('results'));
});

// Submission
$router->get('/submission', [
    'as' => 'submissionForm',
    'uses' => '\App\Http\Controllers\SubmissionController@show'
]);
$router->post('/submission', [
    'as' => 'submission',
    'uses' => '\App\Http\Controllers\SubmissionController@submit'
]);
$router->get('/hlaseni', function () {
    return redirect(route('submissionForm'));
});

// Terms and privacy
$router->get('/terms-and-privacy', [
    'as' => 'termsAndPrivacy',
    'uses' => PageController::class . '@termsAndPrivacy',
]);

// APIv0
$router->addRoute([ 'GET', 'POST' ], '/api/v0/{category}/{endpoint}', [
    'as' => 'apiV0',
    'uses' => '\App\Http\Controllers\ApiV0Controller@route'
]);
