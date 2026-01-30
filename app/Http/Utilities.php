<?php

namespace App\Http;

use Carbon\Carbon;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReCaptcha\ReCaptcha;

use App\Exceptions\ForbiddenException;
use App\Models\Contest;

class Utilities {
    public static function checkRecaptcha()
    {
        // FIXME: Workaround due to some mocking issues in PHPUnit when unit testing
        if (config('app.env') == 'testing') {
            return true;
        }
        if (Auth::check()) {
            return true;
        }
        $recaptchaSecret = config('ctvero.recaptchaSecret');
        if (empty($recaptchaSecret)) {
            self::logRecaptchaWarning('Missing reCAPTCHA secret. Skipping validation.');
            return true;
        }
        $recaptcha = new ReCaptcha($recaptchaSecret);
        $response = $recaptcha->setScoreThreshold(config('ctvero.recaptchaScoreThreshold'))
            ->verify(request()->input('g-recaptcha-response'), request()->ip());
        Log::info('Received reCAPTCHA response:', [ var_export($response, true) ]);
        if (! $response->isSuccess()) {
            throw new ForbiddenException();
        }
        return true;
    }

    private static function logRecaptchaWarning(string $message): void
    {
        $logPath = storage_path('logs/last_exception.txt');
        try {
            $payload = sprintf(
                "class: %s\nmessage: %s\ntimestamp: %s",
                \RuntimeException::class,
                $message,
                date(DATE_ATOM)
            );
            @file_put_contents($logPath, $payload);
        } catch (\Throwable $logException) {
            // Ignore logging failures to avoid cascading errors.
        }
    }

    public static function contestL10n($contestName)
    {
        if (preg_match_all('|^(.*\S)\s+(\d+)\s*$|', $contestName, $output)) {
            return __($output[1][0]) . ' ' . $output[2][0];
        }
        return $contestName;
    }

    public static function normalDateTime($dateTime)
    {
        return date('j. n. Y H:i', strtotime($dateTime));
    }

    public static function contestInProgress($contestName)
    {
        $contestsInProgress = Contest::submissionActiveOrdered();
        return $contestsInProgress->where('name', $contestName)->first() ? '<i> (' . __('průběžné pořadí') . ')</i>' : '';
    }

    public static function submissionDeadline()
    {
        $deadline = Carbon::parse(Contest::submissionActiveOrdered()->min('submission_end'));
        $now = Carbon::now();
        if ($deadline->diffInSeconds($now) > 0) {
            if ($deadline->diffInDays($now) > 0) {
                $diff = $deadline->diffInDays($now);
                $unit = __('dnů');
            } elseif ($deadline->diffInHours($now) > 0) {
                $diff = $deadline->diffInHours($now);
                $unit = __('hodin');
            } else {
                $diff = $deadline->diffInMinutes($now);
                $unit = __('minut');
            }
            return __('Zbývá') . ' ' . $unit . ': ' . $diff;
        }
    }

    public static function getAppContent($section)
    {
        $locale = app('translator')->getLocale();
        return Markdown::parse(Storage::get('content/' . $section . '_' . $locale . '.md'));
    }

    public static function gpsToLocator($lon, $lat)
    {
        $lon += 180;
        $lat += 90;

        $lon1 = chr(65 + intdiv((intval($lon)), 20));
        $lat1 = chr(65 + intdiv((intval($lat)), 10));

        $lon2 = strval(intdiv(intval($lon) % 20, 2));
        $lat2 = strval(intval($lat) % 10);

        $lon3 = chr(65 + floor((fmod($lon, 2)) / (2 / 24)));
        $lat3 = chr(65 + floor((fmod($lat, 1)) / (1 / 24)));

        $locator = $lon1 . $lat1 . $lon2 . $lat2 . $lon3 . $lat3;

        return $locator;
    }

    public static function locatorToGps($locator)
    {
        list($lon1, $lat1, $lon2, $lat2, $lon3, $lat3) = str_split(strtoupper($locator));

        $lon_base = -180;
        $lat_base = -90;

        $lon1_contrib = (ord($lon1) - 65) * 20;
        $lat1_contrib = (ord($lat1) - 65) * 10;

        $lon2_contrib = intval($lon2) * 2;
        $lat2_contrib = intval($lat2);

        $lon3_contrib = (ord($lon3) - 65) * (2 / 24);
        $lat3_contrib = (ord($lat3) - 65) * (1 / 24);

        return [$lon_base + $lon1_contrib + $lon2_contrib + $lon3_contrib + 1 / 24,
                $lat_base + $lat1_contrib + $lat2_contrib + $lat3_contrib + 1 / 48];
    }

    public static function getCsrfToken()
    {
        $csrfToken = Str::random(40);
        Session::put('_csrf', $csrfToken);

        return $csrfToken;
    }

    public static function getSessionSafeToken()
    {
        return hash('sha256', Session::get('_token'));
    }

    public static function validateCsrfToken()
    {
        if (! Session::has('_csrf')) {
            throw new ForbiddenException();
        }
        if (request()->input('_csrf') !== Session::get('_csrf')) {
            throw new ForbiddenException();
        }
        Session::forget('_csrf');

        return true;
    }

    public static function validateSessionSafeToken()
    {
        if (! Session::has('_token')) {
            throw new ForbiddenException();
        }
        if (request()->input('_token') !== hash('sha256', Session::get('_token'))) {
            throw new ForbiddenException();
        }

        return true;
    }

    public static function smartRedirect($preferredUrl = NULL)
    {
        $redirectUrl = $preferredUrl ?? request()->header('referer');
        $redirectUrlWithoutScheme = preg_replace('|^https?://|', '', $redirectUrl);
        $appUrlWithoutScheme = preg_replace('|^https?://|', '', config('app.url'));
        if (str_starts_with($redirectUrlWithoutScheme, $appUrlWithoutScheme)) {
            return redirect($redirectUrl);
        }
        return redirect(route('index'));
    }

    public static function validatorMessages()
    {
        return [
            'email' => __('Pole :attribute obsahuje neplatnou e-mailovou adresu.'),
            'required' => __('Pole :attribute je vyžadováno.'),
            'max' => __('Pole :attribute přesahuje povolenou délku :max znaků.'),
            'unique' => __('Pole :attribute již obsahuje v databázi stejný záznam.'),
            'size' => __('Pole :attribute nemá přesně :size znaků.'),
            'integer' => __('Pole :attribute neobsahuje celočíselnou hodnotu.'),
            'gt' => __('Pole :attribute neobsahuje hodnotu větší než :value.'),
            'between' => __('Pole :attribute neobsahuje hodnotu v rozmezí od :min do :max.'),
        ];
    }
}
