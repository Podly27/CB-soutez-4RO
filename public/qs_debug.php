<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$token = getenv('DIAG_TOKEN');
$requestToken = $_GET['token'] ?? null;
if (! $token || ! is_string($requestToken) || $requestToken !== $token) {
    http_response_code(403);
    echo json_encode([
        'error' => 'forbidden',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit;
}

$response = [
    'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
    'query_string' => $_SERVER['QUERY_STRING'] ?? null,
    'get' => $_GET,
    'https' => !empty($_SERVER['HTTPS']),
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? null,
    'php_self' => $_SERVER['PHP_SELF'] ?? null,
];

echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
