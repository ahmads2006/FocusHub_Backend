<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$image = \App\Models\Image::where('privacy', 'public')->latest()->first();
echo "URL manually computed: " . $image->url . "\n";
echo "URL_HIGH: " . $image->url_high . "\n";
