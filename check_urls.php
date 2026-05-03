<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$image = \App\Models\Image::with('storage')->where('privacy', 'private')->first();
if (!$image) {
    echo "No private images found, checking first available instead.\n";
    $image = \App\Models\Image::with('storage')->first();
}
if (!$image) {
    die("No images found at all\n");
}
$assetService = app(\App\Services\Core\AssetDeliveryService::class);
$isInCloud = !empty($image->storage?->imagekit_file_id) || 
             !empty($image->storage?->imagekit_file_path) || 
             !empty($image->storage?->path) ||
             in_array($image->storage?->disk, ['spaces', 's3']);
$isPublic = $image->privacy === 'public';

echo "ID: " . $image->id . "\n";
echo "Path: " . ($image->storage?->path ?? 'N/A') . "\n";
echo "Disk: " . ($image->storage?->disk ?? 'N/A') . "\n";
echo "Privacy: " . $image->privacy . "\n";
echo "Is In Cloud: " . ($isInCloud ? 'Yes' : 'No') . "\n";
echo "URL (gallery): " . $image->getUrl('gallery') . "\n";
echo "URL (placeholder): " . $image->getUrl('placeholder') . "\n";
echo "URL (thumbnail): " . $image->getUrl('thumbnail') . "\n";
