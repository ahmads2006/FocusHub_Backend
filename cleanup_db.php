<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$disk = \Illuminate\Support\Facades\Storage::disk('s3');
$images = \App\Models\Image::with('storage')->get();

$deletedCount = 0;
foreach ($images as $img) {
    $path = $img->storage->path ?? $img->storage->original_path ?? null;
    if (!$path || !$disk->exists($path)) {
        echo "Deleting missing image ID: {$img->id} (Path: $path)\n";
        if ($img->storage) $img->storage->delete();
        $img->delete();
        $deletedCount++;
    }
}

echo "Deleted $deletedCount missing images from DB.\n";
