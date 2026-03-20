<?php namespace App\Scripts;
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Image;
use App\Models\ImageSettings;

$images = Image::with('settings')->get();
foreach ($images as $img) {
    echo "ID: {$img->id} | Title: {$img->title} | Privacy: {$img->privacy} | Owner: {$img->user_id}\n";
    echo "   Settings: " . ($img->settings ? "Found" : "Missing") . "\n";
    if ($img->settings) {
        echo "   Allow Download: " . ($img->settings->allow_download ? 'TRUE' : 'FALSE') . "\n";
        echo "   Watermark: " . ($img->settings->watermark_on_download ? 'TRUE' : 'FALSE') . "\n";
    }
    echo "   Accessor (allow_download): " . ($img->allow_download ? 'TRUE' : 'FALSE') . "\n";
    echo "---------------------------------\n";
}
