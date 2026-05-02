<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// 1. Check recent public images and their storage records
echo "=== RECENT PUBLIC IMAGES ===\n";
$imgs = DB::table('images')
    ->where('privacy', 'public')
    ->where('is_visible', 1)
    ->latest()
    ->limit(15)
    ->get(['id', 'album_id', 'privacy', 'title', 'moderation_status']);

foreach ($imgs as $i) {
    $storage = DB::table('image_storages')->where('image_id', $i->id)->first();
    $albumTitle = 'NO ALBUM';
    $albumPrivacy = 'N/A';
    if ($i->album_id) {
        $album = DB::table('albums')->where('id', $i->album_id)->first(['title', 'privacy']);
        if ($album) {
            $albumTitle = $album->title;
            $albumPrivacy = $album->privacy;
        } else {
            $albumTitle = 'DELETED_ALBUM';
        }
    }

    $disk = $storage->disk ?? 'NO_STORAGE';
    $path = $storage->path ?? 'NO_PATH';
    $ikPath = $storage->imagekit_file_path ?? 'NO_IK_PATH';
    $ikId = $storage->imagekit_file_id ?? 'NO_IK_ID';

    echo "ID: {$i->id}\n";
    echo "  Title: {$i->title}\n";
    echo "  Album: {$albumTitle} (privacy: {$albumPrivacy})\n";
    echo "  Moderation: {$i->moderation_status}\n";
    echo "  Disk: {$disk}\n";
    echo "  Path: {$path}\n";
    echo "  IK Path: {$ikPath}\n";
    echo "  IK ID: {$ikId}\n";
    echo "---\n";
}

// 2. Count totals
$total = DB::table('images')->where('privacy', 'public')->where('is_visible', 1)->count();
$noStorage = DB::table('images')
    ->where('privacy', 'public')
    ->where('is_visible', 1)
    ->whereNotExists(function ($q) {
        $q->select(DB::raw(1))->from('image_storages')->whereColumn('image_storages.image_id', 'images.id');
    })
    ->count();
$noIk = DB::table('images')
    ->where('privacy', 'public')
    ->where('is_visible', 1)
    ->join('image_storages', 'image_storages.image_id', '=', 'images.id')
    ->whereNull('image_storages.imagekit_file_path')
    ->count();

echo "\n=== SUMMARY ===\n";
echo "Total public visible images: {$total}\n";
echo "Images WITHOUT storage record: {$noStorage}\n";
echo "Images WITHOUT ImageKit path: {$noIk}\n";
