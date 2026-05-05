<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(\App\Services\Security\SharedLinkService::class);
$album = \App\Models\Album::first();
$expiry = now()->addHours(24);

$link = $service->generate($album, $expiry, null, null, 'view', true, false, 'Test');
echo "Generated raw token: " . $link->token . "\n";

$tokenHash = hash('sha256', $link->token);
$data = \Illuminate\Support\Facades\Cache::get("ephemeral_link:{$tokenHash}");

if ($data) {
    echo "Data found in Redis under hash: {$tokenHash}\n";
} else {
    echo "Data NOT FOUND in Redis under hash: {$tokenHash}\n";
    
    // Search all ephemeral links to see what hash was used
    echo "Let's check what hash was actually used:\n";
    // we can't easily search redis keys via cache facade without specific driver access
}
