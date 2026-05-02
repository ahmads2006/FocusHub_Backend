<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$images = \App\Models\Image::where('privacy', 'public')->latest()->limit(3)->get();
echo json_encode($images->toArray(), JSON_PRETTY_PRINT);
