<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

$reportAndExit = static function (string $message): void {
    header('Content-Type: text/plain');
    echo $message;
    exit(1);
};

set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($reportAndExit): bool {
    $reportAndExit(sprintf('%s in %s:%d', $message, $file, $line));
    return true;
});

set_exception_handler(static function (Throwable $exception) use ($reportAndExit): void {
    $reportAndExit(sprintf('%s in %s:%d', $exception->getMessage(), $exception->getFile(), $exception->getLine()));
});

$app = require __DIR__ . '/../bootstrap/app.php';

header('Content-Type: text/plain');
echo "Routes loaded successfully.\n";
