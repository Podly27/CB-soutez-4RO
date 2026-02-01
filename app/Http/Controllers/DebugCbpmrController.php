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

            $useCookies = $request->boolean('use_cookies', true);
            $stage = 'fetch';
            $fetchResult = $this->fetchCbpmr($url, $useCookies);

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
            /** @var CbpmrShareParser $parser */
            $parser = app(CbpmrShareParser::class);
            $parsedResult = $parser->parse($body);

            $stage = 'return_ok';
            return response()->json([
                'ok' => true,
                'stage' => $stage,
                'final_url' => $fetchResult['final_url'] ?? $url,
                'redirect_chain' => $fetchResult['redirect_chain'] ?? null,
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

    public function parse(Request $request)
    {
        header('Content-Type: application/json; charset=utf-8');
        ini_set('display_errors', '0');

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

            $finalUrl = $fetchResult['final_url'] ?? $url;
            $httpCode = $fetchResult['http_code'] ?? null;
            if ($httpCode !== null && (int) $httpCode !== 200) {
                $snippet = trim(mb_substr($fetchResult['body'] ?? '', 0, 500, 'UTF-8'));
                return response()->json([
                    'ok' => false,
                    'error' => 'http_error',
                    'http_code' => (int) $httpCode,
                    'final_url' => $finalUrl,
                    'redirect_chain' => $fetchResult['redirect_chain'] ?? null,
                    'response_snippet' => $snippet,
                    'stage' => $stage,
                ], 200)->header('Content-Type', 'application/json; charset=utf-8');
            }

            if (! Str::contains($finalUrl, '/share/portable/')) {
                return response()->json([
                    'ok' => false,
                    'error' => 'not_portable',
                    'final_url' => $finalUrl,
                    'stage' => $stage,
                ], 200)->header('Content-Type', 'application/json; charset=utf-8');
            }

            $stage = 'parse_dom';
            $html = (string) ($fetchResult['body'] ?? '');
            $hasMetaCharset = preg_match('/<meta[^>]+charset=/i', $html) === 1;
            if (! $hasMetaCharset || ! mb_check_encoding($html, 'UTF-8')) {
                $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
            }

            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);

            $myLocator = trim((string) $xpath->evaluate('string(//*[@id="locator"])'));
            $place = trim((string) $xpath->evaluate('string(//*[@id="place"])'));
            $qsoCountHeader = trim((string) $xpath->evaluate('string(//*[@id="distance"])'));
            $totalKm = trim((string) $xpath->evaluate('string(//*[@id="km"])'));
            $rows = $xpath->query('//table[@id="myTable"]//tbody//tr');
            $rowsFound = $rows ? $rows->length : 0;

            if ($rowsFound === 0) {
                $titleSnippet = trim((string) $xpath->evaluate('string(//title)'));
                $htmlSnippet = trim(mb_substr($html, 0, 600, 'UTF-8'));
                return response()->json([
                    'ok' => false,
                    'error' => 'no_rows_found',
                    'title_snippet' => $titleSnippet,
                    'html_snippet' => $htmlSnippet,
                    'stage' => $stage,
                ], 200)->header('Content-Type', 'application/json; charset=utf-8');
            }

            $stage = 'parse_rows';
            $firstRows = [];
            $maxRows = min(3, $rowsFound);
            for ($i = 0; $i < $maxRows; $i++) {
                $row = $rows->item($i);
                if (! $row) {
                    continue;
                }
                $rowXpath = new \DOMXPath($row->ownerDocument);
                $time = trim((string) $rowXpath->evaluate('string(./td[2]//text())', $row));
                $name = trim((string) $rowXpath->evaluate('string(.//span[contains(@class,"duplicity-name")]//text())', $row));
                $locator = trim((string) $rowXpath->evaluate('string(.//span[contains(@class,"font-caption-locator-small")]//text())', $row));
                $km = trim((string) $rowXpath->evaluate('string(.//p[contains(@class,"span-km")]//text())', $row));
                $note = trim((string) $rowXpath->evaluate('normalize-space(./td[3]//span[contains(@class,"font-ariel")][last()]//text())', $row));

                $rowData = [
                    'time' => $time,
                    'name' => $name,
                    'locator' => $locator,
                    'km' => $km,
                ];
                if ($note !== '') {
                    $rowData['note'] = $note;
                }
                $firstRows[] = $rowData;
            }

            $finalPath = parse_url($finalUrl, PHP_URL_PATH) ?? '';
            $portableId = null;
            if (preg_match('|/share/portable/(\\d+)|', $finalPath, $matches)) {
                $portableId = $matches[1];
            }

            $stage = 'return_ok';
            return response()->json([
                'ok' => true,
                'stage' => $stage,
                'final_url' => $finalUrl,
                'portable_id' => $portableId,
                'my_locator' => $myLocator,
                'place' => $place,
                'qso_count_header' => $qsoCountHeader,
                'total_km' => $totalKm,
                'rows_found' => $rowsFound,
                'first_rows' => $firstRows,
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

    private function fetchCbpmr(string $url, bool $useCookies = true): array
    {
        /** @var CbpmrShareFetcher $fetcher */
        $fetcher = app(CbpmrShareFetcher::class);
        $fetchResult = $fetcher->fetch($url, ['use_cookies' => $useCookies]);

        if (! $fetchResult['ok']) {
            return $fetchResult;
        }

        return array_merge($fetchResult, [
            'http_code' => $fetchResult['http_status'] ?? null,
        ]);
    }
}
