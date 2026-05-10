<?php
// Temporary script to clear Redis stats cache
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$keys = \Illuminate\Support\Facades\Redis::keys('*stats*');
echo count($keys) . " stats keys found\n";
foreach ($keys as $k) {
    $clean = str_replace('laravel_database_', '', $k);
    \Illuminate\Support\Facades\Redis::del($clean);
    echo "Deleted: $clean\n";
}
echo "Done!\n";
