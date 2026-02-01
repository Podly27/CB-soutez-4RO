<?php

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');
libxml_use_internal_errors(true);

require __DIR__ . '/../vendor/autoload.php';
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
} catch (\Throwable $e) {
}

try {
    $server = $_ENV['DIAG_TOKEN'] ?? $_SERVER['DIAG_TOKEN'] ?? getenv('DIAG_TOKEN') ?? null;
    $req = $_GET['token'] ?? '';
    if (!$server || !$req || !hash_equals($server, $req)) {
        echo json_encode(
            [
                'ok' => false,
                'error' => 'forbidden',
                'server_token_set' => (bool) $server,
                'server_len' => strlen((string) $server),
                'req_len' => strlen($req),
            ],
            JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    $url = $_GET['url'] ?? '';
    if (!$url) {
        echo json_encode(['ok' => false, 'error' => 'missing_url']);
        exit;
    }

    $cookieFile = tempnam(sys_get_temp_dir(), 'cbpmr_cookie_');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: cs-CZ,cs;q=0.9,en;q=0.8',
        ],
    ]);

    $body = curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (is_string($cookieFile)) {
        @unlink($cookieFile);
    }

    if ($body === false) {
        echo json_encode([
            'ok' => false,
            'error' => 'curl_error',
            'message' => $curlError,
            'final_url' => $finalUrl,
            'http_code' => $httpCode,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $titleSnippet = null;
    if (preg_match('/<title>(.*?)<\/title>/is', $body, $matches)) {
        $titleSnippet = trim(strip_tags($matches[1]));
        if (strlen($titleSnippet) > 120) {
            $titleSnippet = mb_substr($titleSnippet, 0, 120) . '...';
        }
    }

    if (!$finalUrl || strpos($finalUrl, '/share/portable/') === false) {
        echo json_encode([
            'ok' => false,
            'error' => 'not_portable_url',
            'final_url' => $finalUrl,
            'http_code' => $httpCode,
            'content_type' => $contentType,
            'body_len' => strlen((string) $body),
            'title_snippet' => $titleSnippet,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $portableId = null;
    if (preg_match('~/share/portable/([^/?#]+)~', $finalUrl, $idMatches)) {
        $portableId = $idMatches[1];
    }

    $dom = new DOMDocument();
    $html = $body;
    if (function_exists('mb_convert_encoding')) {
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
    }
    $dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $getText = static function (?DOMNode $node): ?string {
        if (!$node) {
            return null;
        }
        $text = trim($node->textContent);
        return $text !== '' ? $text : null;
    };

    $myLocator = $getText($xpath->query("//*[@id='locator']")->item(0));
    $place = $getText($xpath->query("//*[@id='place']")->item(0));
    $qsoCountHeader = $getText($xpath->query("//*[@id='distance']")->item(0));
    $totalKm = $getText($xpath->query("//*[@id='km']")->item(0));

    $rows = $xpath->query("//table[@id='myTable']//tbody//tr");
    $rowsFound = $rows ? $rows->length : 0;

    $firstRows = [];
    if ($rows) {
        $limit = min(3, $rows->length);
        for ($i = 0; $i < $limit; $i++) {
            $row = $rows->item($i);
            $time = null;
            $td = $xpath->query('.//td[2]', $row);
            if ($td && $td->length > 0) {
                $time = $getText($td->item(0));
            }
            $name = $getText($xpath->query('.//span[contains(@class,"duplicity-name")]', $row)->item(0));
            $locator = $getText($xpath->query('.//span[contains(@class,"font-caption-locator-small")]', $row)->item(0));
            $km = $getText($xpath->query('.//p[contains(@class,"span-km")]', $row)->item(0));
            $firstRows[] = [
                'time' => $time,
                'name' => $name,
                'locator' => $locator,
                'km' => $km,
            ];
        }
    }

    echo json_encode([
        'ok' => true,
        'final_url' => $finalUrl,
        'portable_id' => $portableId,
        'my_locator' => $myLocator,
        'place' => $place,
        'qso_count_header' => $qsoCountHeader,
        'total_km' => $totalKm,
        'rows_found' => $rowsFound,
        'first_rows' => $firstRows,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $trace = $e->getTraceAsString();
    $traceSnippet = $trace;
    if (strlen($traceSnippet) > 1200) {
        $traceSnippet = substr($traceSnippet, 0, 1200) . '...';
    }

    echo json_encode([
        'ok' => false,
        'error' => 'exception',
        'class' => get_class($e),
        'message' => $e->getMessage(),
        'location' => $e->getFile() . ':' . $e->getLine(),
        'trace_snippet' => $traceSnippet,
    ], JSON_UNESCAPED_SLASHES);
}
