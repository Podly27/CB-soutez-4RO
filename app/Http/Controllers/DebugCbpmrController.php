<?php

namespace App\Http\Controllers;

use App\Services\Cbpmr\CbpmrShareService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DebugCbpmrController extends Controller
{
    public function trace()
    {
        return response()->json([
            'ok' => true,
            'hit' => 'DebugCbpmrController@trace',
            'app_env' => env('APP_ENV'),
            'php' => PHP_VERSION,
            'has_diag_token' => (bool) env('DIAG_TOKEN'),
        ], 200);
    }

    public function fetch(Request $request)
    {
        header('Content-Type: application/json; charset=utf-8');
        ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_DEPRECATED);

        $stage = 'start';

        try {
            $serverToken = env('DIAG_TOKEN');
            $reqToken = (string) $request->query('token', '');

            $stage = 'validate';
            if (! $serverToken || ! hash_equals((string) $serverToken, $reqToken)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'forbidden',
                    'stage' => 'validate',
                ], 200)->header('Content-Type', 'application/json; charset=utf-8');
            }

            $stage = 'validate_ok';

            $url = $request->query('url');
            if (! is_string($url) || trim($url) === '') {
                return response()->json([
                    'ok' => false,
                    'error' => 'missing_url',
                    'stage' => $stage,
                ], 200)->header('Content-Type', 'application/json; charset=utf-8');
            }

            $stage = 'parse_url';
            $parsed = parse_url($url);
            $host = is_array($parsed) ? strtolower($parsed['host'] ?? '') : '';
            $path = is_array($parsed) ? ($parsed['path'] ?? '') : '';

            $allowedHosts = ['cbpmr.info', 'www.cbpmr.info'];
            if (! in_array($host, $allowedHosts, true) || ! Str::startsWith($path, '/share/')) {
                return response()->json([
                    'ok' => false,
                    'error' => 'invalid_host',
                    'stage' => $stage,
                ], 200)->header('Content-Type', 'application/json; charset=utf-8');
            }

            $stage = 'fetch';
            $fetchResult = $this->fetchCbpmr($url);

            if (! $fetchResult['ok']) {
                return response()->json([
                    'ok' => false,
                    'error' => 'fetch_failed',
                    'message' => $fetchResult['error'] ?? 'Fetch failed.',
                    'code' => $fetchResult['code'] ?? null,
                    'url' => $fetchResult['url'] ?? $url,
                    'http_status' => $fetchResult['http_code'] ?? null,
                    'redirect_chain' => $fetchResult['redirect_chain'] ?? null,
                    'stage' => $stage,
                ], 200)->header('Content-Type', 'application/json; charset=utf-8');
            }

            $body = $fetchResult['body'] ?? '';
            $bodyLength = strlen($body);
            $snippet = trim(mb_substr($body, 0, 1000, 'UTF-8'));
            $httpStatus = $fetchResult['http_code'] ?? null;

            if (is_numeric($httpStatus) && (int) $httpStatus >= 400) {
                $errorSnippet = trim(mb_substr($body, 0, 500, 'UTF-8'));

                return response()->json([
                    'ok' => false,
                    'error' => 'http_error',
                    'http_status' => (int) $httpStatus,
                    'response_snippet' => $errorSnippet,
                    'stage' => $stage,
                ], 200)->header('Content-Type', 'application/json; charset=utf-8');
            }

            $stage = 'parse_html';
            /** @var CbpmrShareService $service */
            $service = app(CbpmrShareService::class);
            $parsedResult = $service->parsePortableHtml($body, $fetchResult['final_url'] ?? $url);
            $firstRows = [];
            foreach (array_slice($parsedResult['entries'] ?? [], 0, 3) as $entry) {
                $row = [
                    'time' => $entry['time'] ?? null,
                    'name' => $entry['name'] ?? null,
                    'locator' => $entry['locator'] ?? null,
                    'km' => $entry['km_int'] ?? null,
                ];
                if (! empty($entry['note'])) {
                    $row['note'] = $entry['note'];
                }
                $firstRows[] = $row;
            }

            $stage = 'return_ok';
            return response()->json([
                'ok' => true,
                'stage' => $stage,
                'final_url' => $fetchResult['final_url'] ?? $url,
                'redirect_chain' => $fetchResult['redirect_chain'] ?? null,
                'http_status' => $httpStatus,
                'content_type' => $fetchResult['content_type'] ?? null,
                'body_length' => $bodyLength,
                'body_snippet' => $snippet,
                'parsed_preview' => [
                    'my_locator' => $parsedResult['my_locator'] ?? null,
                    'rows_found' => $parsedResult['rows_found'] ?? null,
                    'first_rows' => $firstRows,
                ],
            ], 200)->header('Content-Type', 'application/json; charset=utf-8');
        } catch (\Throwable $e) {
            $logPath = storage_path('logs/last_exception.txt');
            $urlParam = $request->query('url');
            $trace = Str::limit($e->getTraceAsString(), 2000, "\n...truncated...");
            $logMessage = implode("\n", [
                '[' . now()->toDateTimeString() . '] cbpmr-fetch exception',
                'stage: ' . $stage,
                'message: ' . $e->getMessage(),
                'location: ' . $e->getFile() . ':' . $e->getLine(),
                'url_param: ' . (is_string($urlParam) ? $urlParam : json_encode($urlParam)),
                'trace:',
                $trace,
                '',
            ]);
            $logMessage = Str::limit($logMessage, 4000, "\n...truncated...");
            @file_put_contents($logPath, $logMessage, FILE_APPEND);

            return response()->json([
                'ok' => false,
                'error' => 'exception',
                'stage' => $stage,
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'location' => $e->getFile() . ':' . $e->getLine(),
            ], 200)->header('Content-Type', 'application/json; charset=utf-8');
        }
    }

    public function parse(Request $request, CbpmrShareService $service)
    {
        header('Content-Type: application/json; charset=utf-8');
        ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_DEPRECATED);

        if ((string) $request->query('debug') === '1') {
            return response()->json([
                'ok' => true,
                'stage' => 'entered',
                'qs' => $request->query(),
            ], 200)->header('Content-Type', 'application/json; charset=utf-8');
        }

        $stage = 'start';

        try {
            $serverToken = env('DIAG_TOKEN');
            $reqToken = (string) $request->query('token', '');

            $stage = 'validate';
            if (! $serverToken || ! hash_equals((string) $serverToken, $reqToken)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'forbidden',
                    'stage' => 'validate',
                    'server_token_set' => (bool) $serverToken,
                    'server_len' => strlen((string) $serverToken),
                    'req_len' => strlen($reqToken),
                ], 200)->header('Content-Type', 'application/json; charset=utf-8');
            }

            $stage = 'validate_ok';

            $url = $request->query('url');
            if (! is_string($url) || trim($url) === '') {
                return response()->json([
                    'ok' => false,
                    'error' => 'missing_url',
                    'stage' => $stage,
                ], 200)->header('Content-Type', 'application/json; charset=utf-8');
            }

            $stage = 'parse_url';
            $parsed = parse_url($url);
            $host = is_array($parsed) ? strtolower($parsed['host'] ?? '') : '';

            $allowedHosts = ['cbpmr.info', 'www.cbpmr.info'];
            if (! in_array($host, $allowedHosts, true)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'invalid_host',
                    'stage' => $stage,
                ], 200)->header('Content-Type', 'application/json; charset=utf-8');
            }

            $stage = 'fetch';
            $fetchResult = $service->fetch($url);
            $bodySnippet = null;
            if (is_string($fetchResult['body'] ?? null)) {
                $bodySnippet = trim(mb_substr($fetchResult['body'], 0, 500, 'UTF-8'));
            }

            if (! ($fetchResult['ok'] ?? false)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'fetch_failed',
                    'message' => $fetchResult['error'] ?? 'Fetch failed.',
                    'code' => $fetchResult['code'] ?? null,
                    'final_url' => $fetchResult['final_url'] ?? $url,
                    'http_code' => $fetchResult['http_code'] ?? null,
                    'redirect_chain' => $fetchResult['redirect_chain'] ?? null,
                    'content_type' => $fetchResult['content_type'] ?? null,
                    'body_len' => $fetchResult['body_len'] ?? null,
                    'title_snippet' => $fetchResult['title_snippet'] ?? null,
                    'body_snippet' => $bodySnippet,
                    'stage' => $stage,
                ], 200)->header('Content-Type', 'application/json; charset=utf-8');
            }

            $finalUrl = $fetchResult['final_url'] ?? $url;
            $httpCode = $fetchResult['http_code'] ?? null;
            if ($httpCode !== null && (int) $httpCode !== 200) {
                return response()->json([
                    'ok' => false,
                    'error' => 'http_error',
                    'http_code' => (int) $httpCode,
                    'final_url' => $finalUrl,
                    'redirect_chain' => $fetchResult['redirect_chain'] ?? null,
                    'content_type' => $fetchResult['content_type'] ?? null,
                    'body_len' => $fetchResult['body_len'] ?? null,
                    'title_snippet' => $fetchResult['title_snippet'] ?? null,
                    'body_snippet' => $bodySnippet,
                    'stage' => $stage,
                ], 200)->header('Content-Type', 'application/json; charset=utf-8');
            }

            $stage = 'parse';
            $payload = $service->parsePortable((string) ($fetchResult['body'] ?? ''), $finalUrl);

            $stage = 'return_ok';
            return response()->json([
                'ok' => true,
                'stage' => $stage,
                'final_url' => $finalUrl,
                'http_code' => $fetchResult['http_code'] ?? null,
                'redirect_chain' => $fetchResult['redirect_chain'] ?? null,
                'content_type' => $fetchResult['content_type'] ?? null,
                'body_len' => $fetchResult['body_len'] ?? null,
                'title_snippet' => $fetchResult['title_snippet'] ?? null,
                'body_snippet' => $bodySnippet,
                'portable_id' => $payload['portable_id'] ?? null,
                'my_locator' => $payload['my_locator'] ?? null,
                'place' => $payload['place'] ?? null,
                'qso_count_header' => $payload['qso_count_header'] ?? null,
                'total_km' => $payload['total_km'] ?? null,
                'rows_found' => $payload['rows_found'] ?? null,
                'first_rows' => $payload['first_rows'] ?? null,
            ], 200)->header('Content-Type', 'application/json; charset=utf-8');
        } catch (\Throwable $e) {
            $logPath = storage_path('logs/last_exception.txt');
            $urlParam = $request->query('url');
            $trace = Str::limit($e->getTraceAsString(), 2000, "\n...truncated...");
            $logMessage = implode("\n", [
                '[' . now()->toDateTimeString() . '] cbpmr-parse exception',
                'stage: ' . $stage,
                'message: ' . $e->getMessage(),
                'location: ' . $e->getFile() . ':' . $e->getLine(),
                'url_param: ' . (is_string($urlParam) ? $urlParam : json_encode($urlParam)),
                'trace:',
                $trace,
                '',
            ]);
            $logMessage = Str::limit($logMessage, 4000, "\n...truncated...");
            @file_put_contents($logPath, $logMessage, FILE_APPEND);

            return response()->json([
                'ok' => false,
                'error' => 'exception',
                'stage' => $stage,
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'location' => $e->getFile() . ':' . $e->getLine(),
            ], 200)->header('Content-Type', 'application/json; charset=utf-8');
        }
    }

    private function fetchCbpmr(string $url): array
    {
        /** @var CbpmrShareService $service */
        $service = app(CbpmrShareService::class);
        $fetchResult = $service->fetchHtml($url);

        if (! $fetchResult['ok']) {
            return $fetchResult;
        }

        return $fetchResult;
    }
}
