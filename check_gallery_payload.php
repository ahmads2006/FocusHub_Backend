<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Image;

$img = Image::withoutGlobalScopes()
    ->with(['settings', 'user'])
    ->where('privacy', 'public')
    ->latest()
    ->first();

if (!$img) {
    echo "No public image found\n";
    exit(0);
}

$strict = (bool) ($img->settings?->allow_download ?? false);
$img->setAttribute('download_enabled', $strict);
$img->setAttribute('can_download', $strict);
$img->setAttribute('allow_download', $strict);

$arr = $img->toArray();
echo json_encode([
    'id' => $arr['id'] ?? null,
    'title' => $arr['title'] ?? null,
    'allow_download' => $arr['allow_download'] ?? null,
    'can_download' => $arr['can_download'] ?? null,
    'download_enabled' => $arr['download_enabled'] ?? null,
    'settings_allow_download' => $arr['settings']['allow_download'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
