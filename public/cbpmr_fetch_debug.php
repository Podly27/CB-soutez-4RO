<?php

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require __DIR__ . '/../vendor/autoload.php';
    }

    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->safeLoad();
    } catch (\Throwable $e) {
        // ignore, fallback
    }

    $server = $_ENV['DIAG_TOKEN'] ?? $_SERVER['DIAG_TOKEN'] ?? (getenv('DIAG_TOKEN') ?: null);
    $req = $_GET['token'] ?? '';
    if (!$server || !$req || !hash_equals($server, $req)) {
        $envKeys = ['DIAG_TOKEN'];
        $envPresent = array_values(array_intersect($envKeys, array_keys($_ENV)));
        $serverPresent = array_values(array_intersect($envKeys, array_keys($_SERVER)));
        $getenvPresent = getenv('DIAG_TOKEN') !== false ? ['DIAG_TOKEN'] : [];

        echo json_encode([
            'ok' => false,
            'error' => 'forbidden',
            'server_token_set' => (bool) $server,
            'req_token_len' => strlen($req),
            'env_keys_present' => $envPresent,
            'server_keys_present' => $serverPresent,
            'getenv_keys_present' => $getenvPresent,
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

    $useCookies = true;
    if (isset($_GET['use_cookies'])) {
        $useCookies = filter_var($_GET['use_cookies'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $useCookies = $useCookies === null ? true : $useCookies;
    }

    $cookieFile = null;
    if ($useCookies) {
        $cookieFile = sys_get_temp_dir() . '/cbpmr_cookie_' . bin2hex(random_bytes(6)) . '.txt';
    }

    $redirectChain = [];
    $headerHandler = static function ($ch, string $header) use (&$redirectChain): int {
        if (preg_match('/^Location:\\s*(.+)$/i', trim($header), $matches)) {
            $redirectChain[] = trim($matches[1]);
        }

        return strlen($header);
    };

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml',
        ],
        CURLOPT_HEADERFUNCTION => $headerHandler,
    ]);

    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($cookieFile && file_exists($cookieFile)) {
        @unlink($cookieFile);
    }

    if ($body === false) {
        echo json_encode([
            'ok' => false,
            'error' => 'curl_error',
            'message' => $curlError,
            'redirect_chain' => $redirectChain,
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
        'redirect_chain' => $redirectChain,
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
