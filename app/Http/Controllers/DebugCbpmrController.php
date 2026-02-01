<?php

namespace App\Http\Controllers;

use App\Services\Cbpmr\CbpmrShareFetcher;
use App\Services\Cbpmr\CbpmrShareParser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DebugCbpmrController extends Controller
{
    public function fetch(Request $request)
    {
        header('Content-Type: application/json; charset=utf-8');
        ini_set('display_errors', '0');

        $stage = 'start';

        try {
            $serverToken = env('DIAG_TOKEN') ?: env('DIAGTOKEN') ?: env('DIAG_SECRET') ?: env('DEBUG_TOKEN');
            $reqToken = (string) $request->query('token', '');

            if (! $serverToken || ! hash_equals((string) $serverToken, $reqToken)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'forbidden',
                    'stage' => $stage,
                    'server_token_set' => (bool) $serverToken,
                    'server_token_len' => strlen((string) $serverToken),
                    'req_token_len' => strlen($reqToken),
                    'req_has_url' => (bool) $request->query('url'),
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

            $stage = 'http_fetch';
            /** @var CbpmrShareFetcher $fetcher */
            $fetcher = app(CbpmrShareFetcher::class);
            $fetchResult = $fetcher->fetch($url);

            if (! $fetchResult['ok']) {
                return response()->json([
                    'ok' => false,
                    'error' => 'fetch_failed',
                    'message' => $fetchResult['error'] ?? 'Fetch failed.',
                    'code' => $fetchResult['code'] ?? null,
                    'url' => $fetchResult['url'] ?? $url,
                    'http_status' => $fetchResult['http_status'] ?? null,
                    'stage' => $stage,
                ], 200)->header('Content-Type', 'application/json; charset=utf-8');
            }

            $body = $fetchResult['body'] ?? '';
            $bodyLength = strlen($body);
            $snippet = trim(mb_substr($body, 0, 1000, 'UTF-8'));
            $httpStatus = $fetchResult['http_status'] ?? null;

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
            /** @var CbpmrShareParser $parser */
            $parser = app(CbpmrShareParser::class);
            $parsedResult = $parser->parse($body);

            $stage = 'return_ok';
            return response()->json([
                'ok' => true,
                'stage' => $stage,
                'final_url' => $fetchResult['final_url'] ?? $url,
                'http_status' => $httpStatus,
                'content_type' => $fetchResult['content_type'] ?? null,
                'body_length' => $bodyLength,
                'title' => $parsedResult['title'] ?? null,
                'body_snippet' => $snippet,
                'detected_format' => $parsedResult['detected_format'] ?? null,
                'parsed_preview' => $parsedResult['parsed_preview'] ?? null,
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
}
