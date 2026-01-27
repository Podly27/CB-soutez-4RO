<?php

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
    $dbStatus = [
        'ok' => false,
    ];

    try {
        DB::connection()->getPdo();
        $dbStatus['ok'] = true;
    } catch (\Throwable $exception) {
        $dbStatus['error'] = $exception->getMessage();
    }

    return response()->json([
        'status' => $dbStatus['ok'] ? 'ok' : 'degraded',
        'timestamp' => date(DATE_ATOM),
        'app_url' => config('app.url'),
        'db' => $dbStatus,
    ], $dbStatus['ok'] ? 200 : 500);
});

$router->get('/', [
    'as' => 'index',
    'uses' => '\App\Http\Controllers\IndexController@show'
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
