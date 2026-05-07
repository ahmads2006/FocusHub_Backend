<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Events\MessageSent;
use App\Models\Message;

$message = Message::latest()->first();
if (!$message) {
    echo "❌ No messages found in DB to test with.\n";
    exit(1);
}

echo "📢 Triggering MessageSent for message ID: " . $message->id . "\n";
try {
    event(new MessageSent($message));
    echo "✅ Event dispatched successfully!\n";
} catch (\Exception $e) {
    echo "❌ Error triggering event: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
