<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Core\ImageKitService;

$ik = app(ImageKitService::class);
$path = "test.jpg";

echo "Testing ImageKit directly:\n";
echo "No size: " . $ik->getOptimizedUrl($path) . "\n";
echo "With size (400x400): " . $ik->getOptimizedUrl($path, 400, 400) . "\n";
echo "With width only (800): " . $ik->getOptimizedUrl($path, 800) . "\n";
