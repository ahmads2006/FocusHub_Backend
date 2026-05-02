<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

echo "=== CHECKING ACTUAL FILE EXISTENCE ON S3/SPACES ===\n\n";

$images = DB::table('images')
    ->where('privacy', 'public')
    ->where('is_visible', 1)
    ->join('image_storage', 'image_storage.image_id', '=', 'images.id')
    ->latest('images.created_at')
    ->limit(20)
    ->get(['images.id', 'images.title', 'image_storage.path', 'image_storage.imagekit_file_path']);

$missing = [];
$found = [];

foreach ($images as $img) {
    $path = $img->path;
    if (!$path) {
        echo "[NO PATH] {$img->id} | {$img->title}\n";
        $missing[] = $img;
        continue;
    }
    
    // Check if file exists on S3/Spaces
    try {
        $exists = Storage::disk('s3')->exists($path);
    } catch (\Exception $e) {
        $exists = false;
        echo "[ERROR] {$img->id} | {$img->title} | " . $e->getMessage() . "\n";
        continue;
    }
    
    if ($exists) {
        $size = Storage::disk('s3')->size($path);
        echo "[OK  ] {$img->title} | " . round($size/1024) . " KB\n";
        $found[] = $img;
    } else {
        echo "[MISS] {$img->title} | path: {$path}\n";
        $missing[] = $img;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Found on S3: " . count($found) . "\n";
echo "Missing from S3: " . count($missing) . "\n";

if (!empty($missing)) {
    echo "\n=== MISSING IMAGE DETAILS ===\n";
    foreach ($missing as $m) {
        echo "  ID: {$m->id}\n";
        echo "  Title: {$m->title}\n";
        echo "  Path: " . ($m->path ?? 'NULL') . "\n";
        echo "  ---\n";
    }
}
