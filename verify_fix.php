<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test URL generation for all recent public images
$images = App\Models\Image::where('privacy', 'public')
    ->with(['storage', 'album'])
    ->latest()
    ->limit(10)
    ->get();

$service = app(App\Services\Core\AssetDeliveryService::class);

$cdnCount = 0;
$slowCount = 0;

foreach ($images as $img) {
    $url = $service->getUrl($img, 'gallery');
    $isCDN = str_contains($url, 'imagekit') || str_contains($url, 'ik.imagekit');
    
    if ($isCDN) $cdnCount++;
    else $slowCount++;
    
    $route = $isCDN ? 'CDN-FAST' : 'SERVER-SLOW';
    echo "[{$route}] {$img->title}\n";
    echo "  URL: " . substr($url, 0, 100) . "...\n";
}

echo "\n=== RESULT ===\n";
echo "CDN (fast): {$cdnCount}\n";
echo "Server (slow): {$slowCount}\n";

if ($slowCount === 0) {
    echo "ALL IMAGES USE CDN! Problem is FIXED.\n";
} else {
    echo "WARNING: {$slowCount} images still using slow server path.\n";
}
