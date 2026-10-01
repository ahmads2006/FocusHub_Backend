<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SharedLink;

$token = 'wyHPKqlMBKC7MzOy4vQQpm1G0tvH4TiXMI9tFLWJrWe1NYpdJuO6CG1FaiQixSpy';
$tokenHash = hash('sha256', $token);

$link = SharedLink::where('token_hash', $tokenHash)->first();
if (!$link) {
    echo "Link not found by token hash: $tokenHash\n";
    // Search by prefix or first few links
    echo "Listing all shared links in database:\n";
    foreach (SharedLink::all() as $l) {
        echo "ID: {$l->id}, Token (decrypted): {$l->token}, Hash: {$l->token_hash}, Active: " . ($l->is_active ? 'YES' : 'NO') . ", Revoked: " . ($l->isRevoked() ? 'YES' : 'NO') . ", LimitReached: " . ($l->isLimitReached() ? 'YES' : 'NO') . "\n";
    }
} else {
    echo "Found link:\n";
    print_r($link->toArray());
    echo "isExpired: " . ($link->isExpired() ? 'YES' : 'NO') . "\n";
    echo "isRevoked: " . ($link->isRevoked() ? 'YES' : 'NO') . "\n";
    echo "isLimitReached: " . ($link->isLimitReached() ? 'YES' : 'NO') . "\n";
    echo "shareable type: " . ($link->shareable ? get_class($link->shareable) : 'NONE') . "\n";
    
    if ($link->shareable instanceof \App\Models\Album) {
        $album = $link->shareable;
        echo "Album Title: " . $album->title . "\n";
        echo "Photos count (default lazy): " . $album->photos->count() . "\n";
        echo "Photos count (without global scopes): " . \App\Models\Image::withoutGlobalScopes()->where('album_id', $album->id)->count() . "\n";
        
        // Let's test the ZIP generation logic
        echo "Testing ZIP generation logic for Album...\n";
        $items = $album->photos;
        foreach ($items as $image) {
            $disk = $image->storage->disk ?? 'public';
            $path = $image->storage->path ?? null;
            echo "Image ID: {$image->id}, Disk: {$disk}, Path: {$path}, Exists: " . (\Illuminate\Support\Facades\Storage::disk($disk)->exists($path) ? 'YES' : 'NO') . "\n";
        }
    }
}
