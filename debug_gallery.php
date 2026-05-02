<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ALBUMS TABLE COLUMNS ===\n";
$cols = DB::select("SHOW COLUMNS FROM albums");
foreach ($cols as $c) {
    echo $c->Field . " (" . $c->Type . ")\n";
}

echo "\n=== IMAGE_STORAGE TABLE COLUMNS ===\n";
$cols2 = DB::select("SHOW COLUMNS FROM image_storage");
foreach ($cols2 as $c) {
    echo $c->Field . " (" . $c->Type . ")\n";
}

echo "\n=== RECENT PUBLIC IMAGES WITH STORAGE ===\n";
$imgs = DB::table('images')
    ->where('privacy', 'public')
    ->where('is_visible', 1)
    ->latest()
    ->limit(15)
    ->get(['id', 'album_id', 'privacy', 'title', 'moderation_status']);

foreach ($imgs as $i) {
    $storage = DB::table('image_storage')->where('image_id', $i->id)->first();
    $albumTitle = 'NO ALBUM';
    if ($i->album_id) {
        $album = DB::table('albums')->where('id', $i->album_id)->first();
        $albumTitle = $album->title ?? 'DELETED';
    }

    echo "ID: {$i->id} | {$i->title} | Album: {$albumTitle} | Mod: {$i->moderation_status}\n";
    if ($storage) {
        echo "  disk=" . ($storage->disk ?? 'NULL') . " | path=" . ($storage->path ?? 'NULL') . " | ik_path=" . ($storage->imagekit_file_path ?? 'NULL') . " | ik_id=" . ($storage->imagekit_file_id ?? 'NULL') . "\n";
    } else {
        echo "  *** NO STORAGE RECORD ***\n";
    }
    echo "---\n";
}

// Summary counts
$total = DB::table('images')->where('privacy', 'public')->where('is_visible', 1)->count();
$withStorage = DB::table('images')
    ->where('images.privacy', 'public')
    ->where('images.is_visible', 1)
    ->join('image_storage', 'image_storage.image_id', '=', 'images.id')
    ->count();
$withIK = DB::table('images')
    ->where('images.privacy', 'public')
    ->where('images.is_visible', 1)
    ->join('image_storage', 'image_storage.image_id', '=', 'images.id')
    ->whereNotNull('image_storage.imagekit_file_path')
    ->where('image_storage.imagekit_file_path', '!=', '')
    ->count();

echo "\n=== SUMMARY ===\n";
echo "Total public visible: {$total}\n";
echo "With storage record: {$withStorage}\n";
echo "With ImageKit path: {$withIK}\n";
echo "Missing storage: " . ($total - $withStorage) . "\n";
echo "Missing ImageKit: " . ($withStorage - $withIK) . "\n";
