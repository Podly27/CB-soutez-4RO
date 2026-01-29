<?php

declare(strict_types=1);

$tracePath = __DIR__ . '/../storage/logs/route_trace.txt';

header('Content-Type: text/plain');

if (! file_exists($tracePath)) {
    echo "Trace file not found.\n";
    exit(0);
}

if (! is_readable($tracePath)) {
    echo "Trace file is not readable.\n";
    exit(0);
}

echo file_get_contents($tracePath);
