<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Image;
use App\Jobs\ProcessImageThumbnails;
use Illuminate\Support\Facades\Storage;

echo "--- Thumbnail Generation Verification ---\n";

// 1. Find an existing image or create a mock file for testing
$image = Image::first();

if (!$image) {
    echo "❌ No images found in database to test with.\n";
    exit;
}

echo "[Testing] Processing thumbnails for image: {$image->id}...\n";

// 2. Clear old thumbnails if they exist for clean test
$thumbnailsDir = "photos/{$image->id}/thumbnails";
Storage::disk('public')->deleteDirectory($thumbnailsDir);

// 3. Dispatch Job (Executing sync for verification)
try {
    $job = new ProcessImageThumbnails($image);
    $job->handle();
    
    echo "✅ Job executed successfully.\n";

    // 4. Verify Files
    $expected = ['large.webp', 'medium.webp', 'square.webp', 'avatar.webp'];
    foreach ($expected as $file) {
        $path = "{$thumbnailsDir}/{$file}";
        if (Storage::disk('public')->exists($path)) {
            $size = round(Storage::disk('public')->size($path) / 1024, 2);
            echo "✅ Created: {$file} ({$size} KB)\n";
        } else {
            echo "❌ Missing: {$file}\n";
        }
    }

    // 5. Verify Metadata
    $image->refresh();
    if (isset($image->metadata['thumbnails'])) {
        echo "✅ Metadata updated with thumbnail paths.\n";
        print_r($image->metadata['thumbnails']);
    } else {
        echo "❌ Metadata was NOT updated with thumbnail information.\n";
    }

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "--- Verification Complete ---\n";
