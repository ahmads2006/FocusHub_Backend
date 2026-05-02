<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$image = \App\Models\Image::where('privacy', 'public')->latest()->first();
try {
    $arr = $image->toArray();
    echo "toArray successful. Keys: " . implode(', ', array_keys($arr)) . "\n";
} catch (\Exception $e) {
    echo "Exception during toArray: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
