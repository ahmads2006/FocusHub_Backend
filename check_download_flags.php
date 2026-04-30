<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Image;

$rows = Image::withoutGlobalScopes()
    ->with('settings')
    ->where('privacy', 'public')
    ->orderByDesc('created_at')
    ->take(20)
    ->get()
    ->map(function ($img) {
        return [
            'id' => $img->id,
            'title' => $img->title,
            'privacy' => $img->privacy,
            'allow_download_setting' => $img->settings?->allow_download,
            'can_download_accessor' => $img->can_download,
        ];
    })
    ->values();

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
