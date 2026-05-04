<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$album = \App\Models\Album::where('title', 'test')->first();
if ($album) {
    $img = $album->images()->first();
    if ($img) {
        echo "Image URL from frontend appends:\n";
        echo "URL: " . $img->url . "\n";
        echo "Thumbnail: " . $img->url_thumbnail . "\n";
        echo "Storage obj:\n";
        print_r($img->storage->toArray());
    } else {
        echo "No images found.";
    }
} else {
    echo "Album not found.";
}
