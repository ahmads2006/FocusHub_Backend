<?php

$path = __DIR__ . '/storage/logs/laravel.log';
if (!file_exists($path)) {
    echo "laravel.log not found\n";
    exit(0);
}

$keywords = ['AssetAccess:', 'Unauthorized access', 'expired link', 'Image source not found', 'Streaming failed'];
$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$matched = [];

foreach ($lines as $line) {
    foreach ($keywords as $kw) {
        if (stripos($line, $kw) !== false) {
            $matched[] = $line;
            break;
        }
    }
}

$slice = array_slice($matched, -120);
foreach ($slice as $line) {
    echo $line . PHP_EOL;
}
