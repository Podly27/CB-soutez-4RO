<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

use App\Exceptions\AppException;
use App\Http\Utilities;

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
    return response()->json([
        'status' => 'ok',
        'php' => phpversion(),
        'app' => config('app.name'),
        'env' => app()->environment(),
    ]);
});

$router->get('/diag', function () {
    $token = env('DIAG_TOKEN');
    $requestToken = request()->query('token');

    if (! $token || $requestToken !== $token) {
        abort(404);
    }

    return response()->json([
        'storage_framework_exists' => is_dir(storage_path('framework')),
        'storage_writable' => is_writable(storage_path()),
        'bootstrap_cache_writable' => is_writable(base_path('bootstrap/cache')),
        'app_key_set' => strlen((string) config('app.key')) > 0,
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
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            return response()->view('initializing', [
                'message' => 'DB není nastavená nebo není inicializovaná. Dokončete nastavení.',
            ], 200);
        }

        return app(\App\Http\Controllers\IndexController::class)->show();
    }
]);
$router->get('/calendar', [
    'as' => 'calendar',
    'uses' => '\App\Http\Controllers\CalendarController@download'
]);
$router->get('/kalendar', function () {
    return redirect(route('calendar', [ 'contest' => request()->input('soutez') ]));
});
$router->get('/contact', [
    'as' => 'contact', function () {
        return redirect(route('index') . '#contact-message');
    }
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
    'as' => 'lang', function ($lang) {
        if (in_array($lang, config('ctvero.locales'))) {
            request()->session()->put('locale', $lang);
            return Utilities::smartRedirect();
        }
        throw new AppException(422, array(__('Neznámá lokalizace')));
    }
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
    'as' => 'profile', function() {
        if (! Auth::check()) {
            throw new UnauthorizedHttpException('');
        }
        return view('profile')->with([ 'title' => __('Můj profil') ]);
    }
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
    'as' => 'termsAndPrivacy', function() {
        return view('terms-and-privacy')->with([ 'title' => __('Podmínky použití a Zásady ochrany osobních údajů') ]);
    }
]);

// APIv0
$router->addRoute([ 'GET', 'POST' ], '/api/v0/{category}/{endpoint}', [
    'as' => 'apiV0',
    'uses' => '\App\Http\Controllers\ApiV0Controller@route'
]);
