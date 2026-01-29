<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\PageController;
use App\Exceptions\Handler;

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
    $probePath = base_path('storage/logs/_probe.txt');
    $canWriteTestfile = false;
    try {
        $bytes = @file_put_contents($probePath, 'probe');
        if ($bytes !== false) {
            $canWriteTestfile = true;
            @unlink($probePath);
        }
    } catch (\Throwable $e) {
        $canWriteTestfile = false;
    }

    $dbConnectOk = false;
    $dbError = null;
    try {
        DB::connection()->getPdo();
        $dbConnectOk = true;
    } catch (\Throwable $e) {
        $dbConnectOk = false;
        $dbError = sprintf('%s: %s', get_class($e), $e->getMessage());
    }

    return response()->json([
        'status' => 'ok',
        'php' => phpversion(),
        'app_env' => env('APP_ENV'),
        'app_key_present' => (bool) env('APP_KEY'),
        'storage_writable' => is_writable(base_path('storage')),
        'logs_writable' => is_writable(base_path('storage/logs')),
        'cache_writable' => is_writable(base_path('bootstrap/cache')),
        'can_write_testfile' => $canWriteTestfile,
        'db_connect_ok' => $dbConnectOk,
        'db_connect_error' => $dbError,
        'vendor_present' => file_exists(base_path('vendor/autoload.php')),
    ]);
});

$router->get('/diag', function () {
    $token = env('DIAG_TOKEN');
    $requestToken = request()->query('token');

    if (! $token || $requestToken !== $token) {
        abort(404);
    }

    $lastExceptionPath = storage_path('logs/last_exception.txt');
    $lastException = null;
    if (file_exists($lastExceptionPath)) {
        $lastException = file_get_contents($lastExceptionPath);
    }

    return response()->json([
        'storage_framework_exists' => is_dir(storage_path('framework')),
        'storage_writable' => is_writable(storage_path()),
        'bootstrap_cache_writable' => is_writable(base_path('bootstrap/cache')),
        'app_key_set' => strlen((string) config('app.key')) > 0,
        'last_exception' => $lastException,
    ]);
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

    Artisan::call('migrate', ['--force' => true]);
    $output = "== migrate ==\n" . Artisan::output();

    Artisan::call('db:seed', ['--force' => true]);
    $output .= "\n== db:seed ==\n" . Artisan::output();

    file_put_contents($markerPath, 'completed_at=' . date(DATE_ATOM));

    return response($output, 200, ['Content-Type' => 'text/plain']);
});

$router->get('/', [
    'as' => 'index',
    'uses' => function () {
        try {
            return app(PageController::class)->index();
        } catch (\Throwable $e) {
            $errorId = Handler::storeLastException($e);
            return response('INIT ERROR ' . $errorId, 200, [ 'Content-Type' => 'text/plain' ]);
        }
    },
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
