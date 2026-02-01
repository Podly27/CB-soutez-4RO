<?php

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
    require __DIR__ . '/../vendor/autoload.php';

    $server = getenv('DIAG_TOKEN') ?: ($_ENV['DIAG_TOKEN'] ?? null);
    $req = $_GET['token'] ?? '';
    if (!$server || !$req || !hash_equals($server, $req)) {
        echo json_encode([
            'ok' => false,
            'error' => 'forbidden',
            'server_token_set' => (bool) $server,
            'req_token_len' => strlen($req),
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $url = $_GET['url'] ?? '';
    if (!$url) {
        echo json_encode(['ok' => false, 'error' => 'missing_url'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $parts = parse_url($url);
    if (!$parts || !in_array($parts['host'] ?? '', ['www.cbpmr.info', 'cbpmr.info'], true)) {
        echo json_encode([
            'ok' => false,
            'error' => 'invalid_host',
            'host' => $parts['host'] ?? null,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!function_exists('curl_init')) {
        echo json_encode(['ok' => false, 'error' => 'curl_missing'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml',
        ],
    ]);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($body === false) {
        echo json_encode([
            'ok' => false,
            'error' => 'curl_error',
            'message' => $curlError,
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $titleSnippet = null;
    if (preg_match('/<title>(.*?)<\/title>/is', $body, $matches)) {
        $titleSnippet = trim($matches[1]);
    }

    echo json_encode([
        'ok' => true,
        'http_code' => $info['http_code'] ?? null,
        'content_type' => $info['content_type'] ?? null,
        'final_url' => $info['url'] ?? null,
        'body_len' => strlen($body),
        'title_snippet' => $titleSnippet,
        'first_500_chars' => substr($body, 0, 500),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'exception',
        'class' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile() . ':' . $e->getLine(),
    ], JSON_UNESCAPED_SLASHES);
}
