<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SharedLink;

$latest = SharedLink::orderBy("created_at", "desc")->first();
if ($latest) {
    echo "ID: {$latest->id}\n";
    echo "Raw Token (Decrypted): " . $latest->token . "\n";
    echo "Token Hash in DB: " . $latest->token_hash . "\n";
    echo "Calculated Hash: " . hash('sha256', $latest->token) . "\n";
    echo "Match: " . (hash('sha256', $latest->token) === $latest->token_hash ? 'YES' : 'NO') . "\n";
} else {
    echo "No links found.\n";
}
