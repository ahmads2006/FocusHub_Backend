<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$token = 'X2TrW4IdunjYfsASUjxEl4r56t0ILjINbRKO7bwnbWiVzLsAyrvD8XGRSXgIx9TK';
$hash = hash('sha256', $token);
echo "Token: $token\n";
echo "Hash: $hash\n";

$link = \App\Models\SharedLink::where('token_hash', $hash)->first();
if ($link) {
    echo "Found in DB! ID: {$link->id}\n";
} else {
    echo "Not found in DB.\n";
}

$ephemeral = \Illuminate\Support\Facades\Cache::get("ephemeral_link:{$hash}");
if ($ephemeral) {
    echo "Found in Redis!\n";
} else {
    echo "Not found in Redis.\n";
}
