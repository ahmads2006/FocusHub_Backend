<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// List all albums with their privacy
$albums = \App\Models\Album::with('settings')->get();
echo "=== ALL ALBUMS PRIVACY STATUS ===\n";
foreach ($albums as $album) {
    echo sprintf(
        "Album: %-25s | ID: %s | Privacy Accessor: %-8s | Settings Privacy: %-8s | Settings ID: %s\n",
        $album->title,
        $album->id,
        $album->privacy ?? 'NULL',
        $album->settings?->privacy ?? 'NO_SETTINGS',
        $album->settings?->id ?? 'NONE'
    );
}

echo "\n=== ALBUM_SETTINGS TABLE ===\n";
$settings = \DB::table('album_settings')->get();
foreach ($settings as $s) {
    echo sprintf(
        "Settings ID: %s | Album ID: %s | Privacy: %s\n",
        $s->id ?? 'N/A',
        $s->album_id ?? 'N/A',
        $s->privacy ?? 'N/A'
    );
}
