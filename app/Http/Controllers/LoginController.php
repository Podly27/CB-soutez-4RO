<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

use App\Exceptions\AppException;
use App\Http\Utilities;
use App\Models\User;

class LoginController extends Controller
{
    private const LOGIN_REDIRECTS = [
        'facebook' => '/auth/facebook',
        'google' => '/auth/google',
        'twitter' => '/auth/twitter',
    ];

    private const PROVIDER_ALIASES = [
        'fb' => 'facebook',
    ];

    private function normalizeProvider(string $provider): string
    {
        return self::PROVIDER_ALIASES[$provider] ?? $provider;
    }

    private function logRejectedProvider(string $provider): void
    {
        try {
            $payload = sprintf('[%s] provider rejected: %s', date('c'), $provider);
            file_put_contents(storage_path('logs/last_rejected_provider.txt'), $payload);
        } catch (\Throwable $e) {
            // Best effort logging only.
        }
    }

    private function shouldRedirectFromLoginRoute(): bool
    {
        return Str::startsWith(request()->path(), 'login/');
    }

    private function providerConfig(string $provider): array
    {
        switch ($provider) {
            case 'facebook':
                $config = [
                    'client_id' => env('FACEBOOK_APP_ID'),
                    'client_secret' => env('FACEBOOK_APP_SECRET'),
                    'redirect' => env('FACEBOOK_REDIRECT_URI'),
                ];
                break;
            case 'google':
                $config = [
                    'client_id' => env('GOOGLE_CLIENT_ID'),
                    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
                    'redirect' => env('GOOGLE_REDIRECT_URI'),
                ];
                break;
            case 'twitter':
                $config = [
                    'client_id' => env('TWITTER_CLIENT_ID'),
                    'client_secret' => env('TWITTER_CLIENT_SECRET'),
                    'redirect' => env('TWITTER_REDIRECT_URI'),
                ];
                break;
            default:
                throw new AppException(422, array(__('Neznámý nebo nepodporovaný poskytovatel autentizace: :provider', [ 'provider' => $provider ])));
        }

        $missing = [];
        foreach ([ 'client_id', 'client_secret', 'redirect' ] as $key) {
            if (! $config[$key]) {
                $missing[] = $key;
            }
        }

        if ($missing) {
            throw new AppException(500, array(__('Chybí OAuth konfigurace pro :provider (:fields).', [
                'provider' => $provider,
                'fields' => implode(', ', $missing),
            ])));
        }

        return $config;
    }

    private function socialiteDriver(string $provider)
    {
        config([ 'services.' . $provider => $this->providerConfig($provider) ]);

        return Socialite::driver($provider);
    }

    private function logLastException(\Throwable $e): void
    {
        try {
            $previous = $e->getPrevious();
            $traceLines = preg_split('/\r\n|\r|\n/', $e->getTraceAsString());
            $tracePreview = array_slice($traceLines ?: [], 0, 20);
            $payload = [
                'class: ' . get_class($e),
                'message: ' . $e->getMessage(),
                'code: ' . $e->getCode(),
                'previous: ' . ($previous ? sprintf('%s: %s', get_class($previous), $previous->getMessage()) : 'none'),
                'trace:',
                ...$tracePreview,
            ];
            file_put_contents(storage_path('logs/last_exception.txt'), implode(PHP_EOL, $payload));
        } catch (\Throwable $logException) {
            // Best effort logging only.
        }
    }

    private function logOauthException(\Throwable $e): void
    {
        try {
            $responseBody = null;
            if ($e instanceof \GuzzleHttp\Exception\BadResponseException) {
                $response = $e->getResponse();
                if ($response) {
                    $responseBody = (string) $response->getBody();
                }
            } elseif (method_exists($e, 'getResponse')) {
                try {
                    $response = $e->getResponse();
                    if ($response) {
                        $responseBody = (string) $response->getBody();
                    }
                } catch (\Throwable $responseException) {
                    // Ignore response parsing errors.
                }
            }

            $payload = [
                'class: ' . get_class($e),
                'message: ' . $e->getMessage(),
                'code: ' . $e->getCode(),
            ];
            if ($responseBody !== null) {
                $payload[] = 'response_body: ' . $responseBody;
            }
            file_put_contents(storage_path('logs/oauth_last_error.txt'), implode(PHP_EOL, $payload));
        } catch (\Throwable $logException) {
            // Best effort logging only.
        }
    }

    private function shortOauthReason(\Throwable $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            $message = get_class($e);
        }

        return Str::limit($message, 160);
    }

    private function handleOauthFailure(string $provider, \Throwable $e)
    {
        $this->logOauthException($e);
        $this->logLastException($e);
        Log::error('Error with OAuth provider:', [ $provider, $e->getMessage() ]);
        if ($provider === 'facebook') {
            Session::flash('errors', [ 'Facebook login error: ' . $this->shortOauthReason($e) ]);
        } else {
            Session::flash('errors', array(__('Chyba poskytovatele autentizace: :provider', [ 'provider' => $provider ])));
        }

        return redirect('/login');
    }

    public function loginChecks($provider)
    {
        $registeredProviders = [
            'facebook',
            'google',
            'twitter',
        ];
        if (! in_array($provider, $registeredProviders, true)) {
            $this->logRejectedProvider($provider);
            throw new AppException(422, array(__('Neznámý nebo nepodporovaný poskytovatel autentizace: :provider', [ 'provider' => $provider ])));
        }
        if (Auth::check()) {
            Session::flash('infos', array(__('Uživatelský účet je již přihlášen.')));
            return Utilities::smartRedirect();
        }
    }

    private function loginFlow(string $provider)
    {
        if ($this->shouldRedirectFromLoginRoute()) {
            $target = self::LOGIN_REDIRECTS[$provider] ?? null;
            if ($target) {
                return redirect($target);
            }
        }
        $redirect = $this->loginChecks($provider);
        if ($redirect) {
            return $redirect;
        }
        Session::put('redirectUrlAfterLogin', request()->header('referer'));

        try {
            return $this->socialiteDriver($provider)->redirect();
        } catch (\Throwable $e) {
            return $this->handleOauthFailure($provider, $e);
        }
    }

    public function login($provider)
    {
        $provider = $this->normalizeProvider($provider);
        if ($provider === 'facebook') {
            try {
                return $this->loginFlow($provider);
            } catch (\Throwable $e) {
                return $this->handleOauthFailure($provider, $e);
            }
        }

        return $this->loginFlow($provider);
    }

    private function callbackFlow(string $provider)
    {
        if ($this->shouldRedirectFromLoginRoute()) {
            $target = self::LOGIN_REDIRECTS[$provider] ?? null;
            if ($target) {
                return redirect($target . '/callback');
            }
        }
        $redirect = $this->loginChecks($provider);
        if ($redirect) {
            return $redirect;
        }

        try {
            $oauthUser = $this->socialiteDriver($provider)->user();
            Log::debug('Received OAuth user:', [ var_export($oauthUser, true) ]);
        } catch (\Throwable $e) {
            return $this->handleOauthFailure($provider, $e);
        }

        $authInfo = [ 'name' => $oauthUser->getName(), 'email' => $oauthUser->getEmail() ];
        $user = User::where($authInfo)->first();
        if (! $user) {
            Log::info('Create new account for:', [ var_export($authInfo, true) ]);
            $user = User::create([
                'name' => $oauthUser->getName(),
                'email' => $oauthUser->getEmail()
            ]);
            $user->save();
        }
        $user->providers()->updateOrCreate([
            'provider' => $provider,
        ], [
            'provider_uid' => $oauthUser->getId(),
            'avatar_url' => $oauthUser->getAvatar(),
        ]);
        $user->save();

        Auth::login($user);
        Log::info('Login successful for:', [ var_export($authInfo, true) ]);
        Session::flash('successes', array(__('Přihlášení proběhlo úspěšně.')));
        Session::regenerateToken();

        return Utilities::smartRedirect(Session::pull('redirectUrlAfterLogin', NULL));
    }

    public function callback($provider)
    {
        $provider = $this->normalizeProvider($provider);
        if ($provider === 'facebook') {
            try {
                return $this->callbackFlow($provider);
            } catch (\Throwable $e) {
                return $this->handleOauthFailure($provider, $e);
            }
        }

        return $this->callbackFlow($provider);
    }

    public function logout()
    {
        if (! Auth::check()) {
            Session::flash('infos', array(__('Uživatelský účet nebyl přihlášen.')));
            return Utilities::smartRedirect();
        }

        $userInfo = [ Auth::user()->name, Auth::user()->email ];
        Auth::logout();
        Log::info('Logout successful for:', [ var_export($userInfo, true) ]);
        Session::flash('successes', array(__('Odhlášení proběhlo úspěšně.')));
        Session::regenerateToken();

        return Utilities::smartRedirect();
    }

    public function loginGoogle()
    {
        return $this->login('google');
    }

    public function loginGoogleCallback()
    {
        return $this->callback('google');
    }

    public function loginFacebook()
    {
        return $this->login('facebook');
    }

    public function loginFacebookCallback()
    {
        return $this->callback('facebook');
    }

    public function loginTwitter()
    {
        return $this->login('twitter');
    }

    public function loginTwitterCallback()
    {
        return $this->callback('twitter');
    }
}
