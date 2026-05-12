<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    \Illuminate\Support\Facades\DB::select('SELECT 1 FROM media LIMIT 1');
    echo "EXISTS";
} catch (\Exception $e) {
    echo "MISSING: " . $e->getMessage();
}
