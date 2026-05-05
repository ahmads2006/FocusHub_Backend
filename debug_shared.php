<?php
// Debug script for shared album images
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SharedLink;
use App\Models\Image;
use App\Models\Album;

// Get all shared links
$links = SharedLink::all();
echo "=== SHARED LINKS (" . $links->count() . ") ===\n";

foreach ($links as $link) {
    echo "---\n";
    echo "ID: " . $link->id . "\n";
    echo "Type: " . $link->shareable_type . "\n";
    echo "Shareable ID: " . $link->shareable_id . "\n";
    echo "Active: " . ($link->is_active ? 'YES' : 'NO') . "\n";
    echo "Expires: " . ($link->expires_at ?? 'NEVER') . "\n";
    echo "Access: " . $link->access_count . "/" . ($link->max_access ?? 'unlimited') . "\n";
    
    $shareable = $link->shareable;
    if ($shareable instanceof Album) {
        echo "Album Title: " . $shareable->title . "\n";
        
        // Count images WITH global scopes (normal)
        $normalCount = $shareable->images()->count();
        echo "Images (with scopes): " . $normalCount . "\n";
        
        // Count images WITHOUT global scopes
        $allCount = Image::withoutGlobalScopes()
            ->where('album_id', $shareable->id)
            ->count();
        echo "Images (without scopes): " . $allCount . "\n";
        
        // Check visibility of each image
        $images = Image::withoutGlobalScopes()
            ->where('album_id', $shareable->id)
            ->get();
        foreach ($images as $img) {
            echo "  IMG: " . $img->id . " | visible: " . ($img->is_visible ? 'Y' : 'N') . " | url: " . substr($img->url ?? 'NULL', 0, 80) . "\n";
        }
    } else {
        echo "Shareable: " . ($shareable ? get_class($shareable) : 'NULL') . "\n";
    }
}
