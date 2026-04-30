<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Image;

$img = Image::withoutGlobalScopes()
    ->with(['settings', 'storage'])
    ->where('privacy', 'public')
    ->latest()
    ->first();

if (!$img) {
    echo "No public image found\n";
    exit(0);
}

$strictDownload = (bool) ($img->settings?->allow_download ?? false);
$img->setAttribute('download_enabled', $strictDownload);
$img->setAttribute('can_download', $strictDownload);
$img->setAttribute('allow_download', $strictDownload);

$arr = $img->toArray();

echo json_encode([
    'id' => $arr['id'] ?? null,
    'title' => $arr['title'] ?? null,
    'url' => $arr['url'] ?? null,
    'url_tiny' => $arr['url_tiny'] ?? null,
    'url_thumbnail' => $arr['url_thumbnail'] ?? null,
    'original_url' => $arr['original_url'] ?? null,
    'privacy' => $arr['privacy'] ?? null,
    'allow_download' => $arr['allow_download'] ?? null,
    'can_download' => $arr['can_download'] ?? null,
    'download_enabled' => $arr['download_enabled'] ?? null,
    'storage_path' => $arr['storage']['path'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
