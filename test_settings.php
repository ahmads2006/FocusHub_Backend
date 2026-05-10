<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$image = App\Models\Image::latest()->first();
echo json_encode($image->settings->toArray(), JSON_PRETTY_PRINT);
