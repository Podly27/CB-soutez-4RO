<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'ok' => true,
    'php' => PHP_VERSION,
    'ts' => date('c'),
]);
