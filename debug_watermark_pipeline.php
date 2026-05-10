<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Image;

// Check the latest image or a specific image
$imageId = $argv[1] ?? null;
$image = $imageId ? Image::find($imageId) : Image::latest()->first();

if (!$image) {
    die("Image not found\n");
}
echo "=== Image ===\n";
echo "ID: {$image->id}\n";

// Check the settings relationship
$image->load('settings');
echo "Settings record: " . json_encode($image->settings, JSON_PRETTY_PRINT) . "\n";
echo "watermark_on_download (from settings): " . json_encode($image->settings?->watermark_on_download) . "\n";
echo "watermark_on_download (appended attr): " . json_encode($image->watermark_on_download) . "\n";

// Check what the API actually returns
$image->load(['user', 'storage']);
$path = $image->storage?->imagekit_file_path ?? $image->storage?->path;
echo "\nStorage path: {$path}\n";
echo "User name: " . ($image->user->name ?? 'N/A') . "\n";

// Test what the DownloadController would do
$imageKitService = app(\App\Services\Core\ImageKitService::class);
$watermarkText = $image->user->name ?? 'OpalShot';

// Clean path
if ($path) {
    $path = ltrim($path, '/');
    if (str_starts_with(strtolower($path), 'opticvault/')) {
        $path = substr($path, strlen('opticvault/'));
    }
}

echo "\nCleaned path: {$path}\n";
echo "Watermark text: {$watermarkText}\n";

$url = $imageKitService->getWatermarkedUrl($path, $watermarkText, true, 30);
echo "\n=== Generated Watermark URL ===\n{$url}\n";

// Also check what JSON the API actually sends
echo "\n=== Full Image JSON (what API returns) ===\n";
$apiData = $image->toArray();
echo "allow_download: " . json_encode($apiData['allow_download'] ?? 'NOT SET') . "\n";
echo "watermark_on_download: " . json_encode($apiData['watermark_on_download'] ?? 'NOT SET') . "\n";
