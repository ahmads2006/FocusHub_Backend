<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tokenHash = hash('sha256', 'PD6pcdxRDOP47mT7Cel0CybhOkGggD4eb20afKlFRABUXrntPYHh2VQhxrzFg3an');
$ephemeralData = \Illuminate\Support\Facades\Cache::get("ephemeral_link:{$tokenHash}");

if ($ephemeralData) {
    echo "Found in Redis!\n";
    $link = new \App\Models\SharedLink($ephemeralData);
    $link->token = 'PD6pcdxRDOP47mT7Cel0CybhOkGggD4eb20afKlFRABUXrntPYHh2VQhxrzFg3an';
    $shareable = $link->shareable;
    if ($shareable) {
        echo "Shareable found: " . get_class($shareable) . "\n";
    } else {
        echo "Shareable NOT found!\n";
    }
} else {
    echo "Not found in Redis.\n";
    $link = \App\Models\SharedLink::where('token_hash', $tokenHash)->first();
    if ($link) {
        echo "Found in DB!\n";
        $shareable = $link->shareable;
        if ($shareable) {
            echo "Shareable found: " . get_class($shareable) . "\n";
        } else {
            echo "Shareable NOT found!\n";
        }
    } else {
        echo "Not found in DB either.\n";
    }
}
