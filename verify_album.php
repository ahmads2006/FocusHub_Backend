<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Album;
use App\Models\Image;

echo "\n--- OpticVault: Automatic Album Verification Tool ---\n";

$albumId = $argv[1] ?? null;

if (!$albumId) {
    echo "Usage: php verify_album.php [ALBUM_ID]\n";
    echo "Listing recent albums:\n";
    $recent = Album::latest()->limit(5)->get();
    foreach($recent as $a) {
        echo " - [ID: {$a->id}] {$a->title} (Images: {$a->images()->count()})\n";
    }
    exit;
}

$album = Album::find($albumId);
if (!$album) {
    echo "❌ Error: Album not found.\n";
    exit;
}

echo "Checking Album: {$album->title}\n";
$images = $album->images()->with('moderation')->get();

$stats = [
    'approved' => 0,
    'pending_review' => 0,
    'rejected' => 0,
    'total' => $images->count()
];

foreach($images as $img) {
    if (!$img->moderation) {
         echo "⚠️ Warning: Image #{$img->id} ({$img->filename}) has NO moderation record!\n";
         continue;
    }
    $status = $img->moderation->status;
    $stats[$status] = ($stats[$status] ?? 0) + 1;
    
    if ($status === 'rejected') {
        echo "🔴 REJECTED: {$img->filename} - Reason: {$img->moderation->sensitivity_reason}\n";
    } elseif ($status === 'pending_review') {
        echo "🟡 PENDING: {$img->filename} - Waiting for sensitive content review.\n";
    }
}

echo "\n--- RESULTS ---\n";
echo "✅ Approved: " . $stats['approved'] . "\n";
echo "🟡 Pending:  " . $stats['pending_review'] . "\n";
echo "🔴 Rejected: " . $stats['rejected'] . "\n";
echo "📦 Total:    " . $stats['total'] . "\n";

if ($stats['rejected'] > 0) {
    echo "\n💡 Tip: Rejected images are stored in 'storage/app/quarantine' for admin review.\n";
}
echo "---------------------------------\n";
