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

        $token = request()->input('g-recaptcha-response');
        if (! is_string($token) || trim($token) === '') {
            throw new ForbiddenException();
        }

        $projectId = config('ctvero.recaptchaEnterpriseProjectId');
        $apiKey = config('ctvero.recaptchaEnterpriseApiKey');

        if (is_string($projectId) && trim($projectId) !== '' && is_string($apiKey) && trim($apiKey) !== '') {
            self::verifyRecaptchaEnterprise($token, trim($projectId), trim($apiKey));
            return true;
        }

        $recaptchaSecret = config('ctvero.recaptchaSecret');
        if (empty($recaptchaSecret)) {
            self::logRecaptchaWarning('Missing reCAPTCHA credentials. Skipping validation.');
            return true;
        }

        $recaptcha = new ReCaptcha($recaptchaSecret);
        $response = $recaptcha->setScoreThreshold((float) config('ctvero.recaptchaScoreThreshold'))
            ->verify($token, request()->ip());
        Log::info('Received reCAPTCHA response:', [ var_export($response, true) ]);
        if (! $response->isSuccess()) {
            throw new ForbiddenException();
        }

        return true;
    }

    private static function verifyRecaptchaEnterprise(string $token, string $projectId, string $apiKey): void
    {
        $endpoint = sprintf(
            'https://recaptchaenterprise.googleapis.com/v1/projects/%s/assessments?key=%s',
            rawurlencode($projectId),
            rawurlencode($apiKey)
        );

        $expectedAction = (string) config('ctvero.recaptchaExpectedAction', 'submit');
        $siteKey = (string) config('ctvero.recaptchaSiteKey');
        if ($siteKey === '') {
            self::logRecaptchaWarning('Missing reCAPTCHA site key for enterprise verification.');
            throw new ForbiddenException();
        }

        $body = [
            'event' => [
                'token' => $token,
                'siteKey' => $siteKey,
                'expectedAction' => $expectedAction,
                'userIpAddress' => request()->ip(),
                'userAgent' => request()->userAgent(),
            ],
        ];

        $responsePayload = self::postJson($endpoint, $body);

        $tokenProperties = $responsePayload['tokenProperties'] ?? [];
        if (($tokenProperties['valid'] ?? false) !== true) {
            Log::warning('reCAPTCHA Enterprise rejected token.', [
                'invalid_reason' => $tokenProperties['invalidReason'] ?? null,
                'action' => $tokenProperties['action'] ?? null,
            ]);
            throw new ForbiddenException();
        }

        if (($tokenProperties['action'] ?? null) !== $expectedAction) {
            Log::warning('reCAPTCHA Enterprise action mismatch.', [
                'expected_action' => $expectedAction,
                'actual_action' => $tokenProperties['action'] ?? null,
            ]);
            throw new ForbiddenException();
        }

        $score = $responsePayload['riskAnalysis']['score'] ?? null;
        $threshold = (float) config('ctvero.recaptchaScoreThreshold', 0.5);
        if (! is_numeric($score) || (float) $score < $threshold) {
            Log::warning('reCAPTCHA Enterprise risk score below threshold.', [
                'score' => $score,
                'threshold' => $threshold,
                'reasons' => $responsePayload['riskAnalysis']['reasons'] ?? [],
            ]);
            throw new ForbiddenException();
        }
    }

    private static function postJson(string $url, array $body): array
    {
        $payload = json_encode($body, JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new ForbiddenException();
        }

        $ch = curl_init($url);
        if (! $ch) {
            throw new ForbiddenException();
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $rawBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = $rawBody === false ? curl_error($ch) : null;
        curl_close($ch);

        if ($rawBody === false) {
            Log::warning('reCAPTCHA Enterprise request failed.', [
                'error' => $curlError,
            ]);
            throw new ForbiddenException();
        }

        $decoded = json_decode($rawBody, true);
        if (! is_array($decoded) || $httpCode < 200 || $httpCode >= 300) {
            Log::warning('reCAPTCHA Enterprise returned invalid response.', [
                'http_code' => $httpCode,
                'response' => is_array($decoded) ? $decoded : $rawBody,
            ]);
            throw new ForbiddenException();
        }

        return $decoded;
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

    public static function isAdmin(?string $userEmail): bool
    {
        if (! $userEmail) {
            return false;
        }

        $adminEmails = env('ADMIN_EMAILS');
        if (! $adminEmails) {
            $adminEmails = env('ADMIN_EMAIL');
        }

        if (! is_string($adminEmails) || $adminEmails === '') {
            return false;
        }

        $emails = array_filter(array_map('trim', explode(',', $adminEmails)));
        $emails = array_map('strtolower', $emails);

        return in_array(strtolower(trim($userEmail)), $emails, true);
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
