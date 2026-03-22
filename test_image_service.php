<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$testImagePath = __DIR__ . '/test_red_block.jpg';
if (!file_exists($testImagePath)) {
    echo "NO TEST IMAGE FOUND\n"; exit;
}

$file = new \Illuminate\Http\UploadedFile($testImagePath, 'test.jpg', 'image/jpeg', null, true);

$safety = app(\App\Services\AI\ContentSafetyService::class);
$res = $safety->validate($file);

echo "ContentSafetyService->validate() returned: \n";
print_r($res);
