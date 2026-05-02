<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Fix images that have path but no imagekit_file_path
$fixed = DB::table('image_storage')
    ->whereNotNull('path')
    ->where('path', '!=', '')
    ->where(function($q) {
        $q->whereNull('imagekit_file_path')->orWhere('imagekit_file_path', '');
    })
    ->update(['imagekit_file_path' => DB::raw('path')]);

echo "Fixed {$fixed} image(s) - copied path to imagekit_file_path\n";

// Also rename any 'Batch Upload ...' albums to 'Quick Uploads' to consolidate
$renamed = DB::table('albums')
    ->where('title', 'like', 'Batch Upload%')
    ->update(['title' => 'Quick Uploads']);

echo "Renamed {$renamed} 'Batch Upload' album(s) to 'Quick Uploads'\n";

// Now deduplicate: merge images from duplicate Quick Uploads albums per user
$users = DB::table('albums')
    ->where('title', 'Quick Uploads')
    ->select('user_id', DB::raw('COUNT(*) as cnt'), DB::raw('MIN(id) as keep_id'))
    ->groupBy('user_id')
    ->having('cnt', '>', 1)
    ->get();

foreach ($users as $u) {
    $duplicateIds = DB::table('albums')
        ->where('user_id', $u->user_id)
        ->where('title', 'Quick Uploads')
        ->where('id', '!=', $u->keep_id)
        ->pluck('id');

    // Move images to the main album
    $moved = DB::table('images')
        ->whereIn('album_id', $duplicateIds)
        ->update(['album_id' => $u->keep_id]);

    // Delete empty duplicates
    $deleted = DB::table('albums')->whereIn('id', $duplicateIds)->delete();

    echo "User {$u->user_id}: moved {$moved} images, deleted {$deleted} duplicate album(s)\n";
}

echo "\nDone! All fixes applied.\n";
