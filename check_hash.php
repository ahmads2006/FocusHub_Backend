<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$link = \App\Models\SharedLink::latest()->first();
if ($link) {
    echo "ID: " . $link->id . "\n";
    echo "Persistent ID: " . $link->persistent_id . "\n";
    echo "Token Hash (DB): " . $link->token_hash . "\n";
    
    // Calculate hash of RAW token
    $rawToken = $link->token;
    $calculatedHash = hash('sha256', $rawToken);
    echo "Calculated Hash of raw token: " . $calculatedHash . "\n";
    
    if ($link->token_hash === $calculatedHash) {
        echo "✅ MATCH!\n";
    } else {
        echo "❌ MISMATCH!\n";
    }
} else {
    echo "No links found.\n";
}
