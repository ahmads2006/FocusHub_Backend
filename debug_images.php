<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get sample images with their storage data
$images = \App\Models\Image::with(['storage', 'user'])
    ->take(4)
    ->get();

echo "Total images in DB: " . \App\Models\Image::count() . "\n";
echo "Public images: " . \App\Models\Image::where('privacy', 'public')->count() . "\n\n";

foreach ($images as $img) {
    echo "=== Image ID: {$img->id} ===\n";
    echo "Title: {$img->title}\n";
    echo "Privacy: {$img->privacy}\n";
    echo "Status: " . ($img->status ?? 'null') . "\n";
    echo "Storage path: " . ($img->storage->path ?? 'NULL') . "\n";
    echo "Storage original_path: " . ($img->storage->original_path ?? 'NULL') . "\n";
    echo "User: " . ($img->user->name ?? 'NULL') . "\n";
    
    // Build the expected CDN URL
    $path = $img->storage->path ?? $img->storage->original_path ?? '';
    echo "Expected CDN URL: https://assets.opalshot.studio/{$path}\n";
    echo "\n";
}
