<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Image;
use App\Services\Core\ImageKitService;
use Illuminate\Support\Facades\Log;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Get a recent image
$image = Image::latest()->first();

if (!$image) {
    echo "No images found in database.\n";
    exit;
}

echo "Testing Watermark for Image ID: " . $image->id . "\n";
echo "Image Path: " . $image->storage?->path . "\n";
echo "Photographer: " . ($image->user->name ?? 'N/A') . "\n";

$service = app(ImageKitService::class);
$text = $image->user->name ?? 'OpalShot';

$path = $image->storage?->imagekit_file_path ?? $image->storage?->path;
if ($path) {
    $path = ltrim($path, '/');
    if (str_starts_with(strtolower($path), 'opticvault/')) {
        $path = substr($path, strlen('opticvault/'));
    }
}

$url = $service->getWatermarkedUrl($path, $text, false, 60);
// Remove ik-attachment for visual check
$url = str_replace('ik-attachment=true', 'visual=true', $url);

echo "\nGenerated URL (Visual Check):\n" . $url . "\n\n";

$url_raw = $service->getWatermarkedUrl($path, $text, false, 60);
echo "Base64 URL: " . $url_raw . "\n";

// Manual URL encoded test
$ot_manual = urlencode($text);
$url_manual = "https://ik.imagekit.io/OPTICVAULT/tr:ot-" . $ot_manual . ",ots-150,otc-FFFFFF,oa-80,of-bottom_right/".$path;
echo "Manual URL Encoded (No Base64): " . $url_manual . "\n";

// Manual Base64 URL Safe test
$ot_b64safe = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
$url_b64safe = "https://ik.imagekit.io/OPTICVAULT/tr:ot-" . $ot_b64safe . ",ote-true,ots-150,otc-FFFFFF,oa-80,of-bottom_right/".$path;
echo "Manual Base64 URL Safe: " . $url_b64safe . "\n";
