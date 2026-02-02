<?php

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
$router->get('/health', [
    'as' => 'health',
    'uses' => 'SystemController@health',
]);

$debugPrefixes = ['/_debug', '/public/_debug'];

foreach ($debugPrefixes as $debugPrefix) {
    $router->get("{$debugPrefix}/response", 'DebugRoutesController@response');
    $router->get("{$debugPrefix}/ping-json", 'DebugRoutesController@pingJson');
    $router->get("{$debugPrefix}/echo-qs", 'DebugRoutesController@echoQs');
    $router->get("{$debugPrefix}/trace", 'DebugCbpmrController@trace');
    $router->get("{$debugPrefix}/cbpmr-fetch", 'DebugCbpmrController@fetch');
    $router->get("{$debugPrefix}/cbpmr-parse", 'DebugCbpmrController@parse');
    $router->get("{$debugPrefix}/submission-dry-run", 'SubmissionController@dryRun');
    $router->get("{$debugPrefix}/route-trace", 'DebugRoutesController@routeTrace');
    $router->get("{$debugPrefix}/routes", 'DebugRoutesController@routes');
    $router->get("{$debugPrefix}/routes-auth", 'DebugRoutesController@routesAuth');
    $router->get("{$debugPrefix}/db-schema", 'DebugRoutesController@dbSchema');
    $router->get("{$debugPrefix}/mail", 'DebugRoutesController@mail');
}

$router->get('/diag', 'SystemController@diag');

$router->get('/_setup/migrate', 'SystemController@setupMigrate');

$router->get('/', [
    'as' => 'index',
    'uses' => 'PageController@indexSafe',
]);
$router->get('/calendar', [
    'as' => 'calendar',
    'uses' => 'CalendarController@download'
]);
$router->get('/kalendar', 'SystemController@redirectCalendar');
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

// Admin
$router->group([
    'middleware' => 'admin',
], function () use ($router) {
    $router->get('/admin', [
        'as' => 'adminDashboard',
        'uses' => 'AdminController@dashboard',
    ]);
    $router->get('/admin/contests', [
        'as' => 'adminContests',
        'uses' => 'AdminController@contestsIndex',
    ]);
    $router->get('/admin/contests/create', [
        'as' => 'adminContestCreate',
        'uses' => 'AdminController@contestsCreate',
    ]);
    $router->post('/admin/contests', [
        'as' => 'adminContestStore',
        'uses' => 'AdminController@contestsStore',
    ]);
    $router->get('/admin/contests/{id}/edit', [
        'as' => 'adminContestEdit',
        'uses' => 'AdminController@contestsEdit',
    ]);
    $router->post('/admin/contests/{id}', [
        'as' => 'adminContestUpdate',
        'uses' => 'AdminController@contestsUpdate',
    ]);
    $router->get('/admin/diaries', [
        'as' => 'adminDiaries',
        'uses' => 'AdminController@diariesIndex',
    ]);
    $router->get('/admin/diaries/{id}/edit', [
        'as' => 'adminDiaryEdit',
        'uses' => 'AdminController@diariesEdit',
    ]);
    $router->post('/admin/diaries/{id}', [
        'as' => 'adminDiaryUpdate',
        'uses' => 'AdminController@diariesUpdate',
    ]);
    $router->post('/admin/diaries/{id}/delete', [
        'as' => 'adminDiaryDelete',
        'uses' => 'AdminController@diariesDelete',
    ]);
});

// Results
$router->get('/results', [
    'as' => 'results',
    'uses' => 'ResultsController@show'
]);
$router->get('/vysledky', 'SystemController@redirectResults');

// Submission
$router->get('/submission', [
    'as' => 'submissionForm',
    'uses' => 'SubmissionController@show'
]);
$router->get('/submission/reset/{resetStep}', [
    'uses' => 'SubmissionController@show'
]);
$router->post('/submission', [
    'as' => 'submissionSubmit',
    'uses' => 'SubmissionController@submit'
]);
$router->get('/hlaseni', 'SystemController@redirectSubmission');

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
