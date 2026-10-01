<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SharedLink;
use Illuminate\Support\Str;

$link = SharedLink::first();
if (!$link) {
    echo "No shared link found in database.\n";
    exit(1);
}

$sessionId = md5('127.0.0.1UserAgent');
$newToken = Str::random(64);

echo "Rotating token for link ID: " . $link->id . "\n";
echo "New Token: " . $newToken . "\n";
echo "New Token Hash: " . hash('sha256', $newToken) . "\n";

$link->update([
    'session_id' => $sessionId,
    'token' => $newToken,
]);

// Clear query cache if any
$updatedLink = SharedLink::where('token_hash', hash('sha256', $newToken))->first();

if (!$updatedLink) {
    echo "ERROR: Updated link not found by token_hash!\n";
    // Let's try searching by ID to see what token_hash was actually saved
    $byId = SharedLink::find($link->id);
    echo "Token Hash in DB for ID: " . ($byId->token_hash ?? 'null') . "\n";
} else {
    echo "Found updated link by token_hash!\n";
    echo "Original Session ID: " . $sessionId . "\n";
    echo "Saved Session ID: " . $updatedLink->session_id . "\n";
    echo "Is Session ID Match: " . ($updatedLink->session_id === $sessionId ? 'YES' : 'NO') . "\n";
}
