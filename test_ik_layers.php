<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$imagekit = new \ImageKit\ImageKit(
    config('services.imagekit.public_key'),
    config('services.imagekit.private_key'),
    config('services.imagekit.url_endpoint')
);

$path = "photos/2026/05/019db09f-8003-7147-9228-2997ca332849/optic_69ff9ad701927_mobil.jpg";
$text = "Ahmad Hrob";
$b64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));

// The raw string method for layers
$raw_layer = "l-text,i-$b64,ie-true,fs-150,co-FFFFFF,l-end";

$url = $imagekit->url([
    'path' => $path,
    'signed' => true,
    'expireSeconds' => 600,
    'transformation' => [
        [
            'raw' => $raw_layer
        ]
    ]
]);

echo "Layers URL: " . $url . "\n";
