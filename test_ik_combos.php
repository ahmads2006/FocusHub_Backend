<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Image;
use App\Services\Core\ImageKitService;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$image = Image::latest()->first();
$path = $image->storage?->path;
if (str_starts_with($path, 'photos/')) {
    // ok
} else {
    $path = "photos/2026/05/019db09f-8003-7147-9228-2997ca332849/optic_69ff9ad701927_mobil.jpg";
}

$imagekit = new \ImageKit\ImageKit(
    config('services.imagekit.public_key'),
    config('services.imagekit.private_key'),
    config('services.imagekit.url_endpoint')
);

$tests = [
    "Simple" => ["ot" => "test"],
    "Font" => ["ot" => "test", "otf" => "Arimo"],
    "Color" => ["ot" => "test", "otc" => "FF0000"],
    "Base64" => ["ot" => base64_encode("Ahmad"), "ote" => "true"],
    "Combined" => ["ot" => base64_encode("Ahmad"), "ote" => "true", "ots" => "100", "otc" => "FFFFFF", "oa" => "90", "of" => "bottom_right"],
];

foreach ($tests as $name => $trans) {
    $url = $imagekit->url([
        'path' => $path,
        'signed' => true,
        'expireSeconds' => 600,
        'transformation' => [$trans]
    ]);
    echo "$name: $url\n\n";
}
