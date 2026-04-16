<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    \Illuminate\Support\Facades\DB::connection('mongodb')->table('chat_messages')->insert([
        '_id' => (string) \Illuminate\Support\Str::uuid(),
        'sender_id' => 'system-test-sender',
        'receiver_id' => 'system-test-receiver',
        'body' => 'Hello from OpalShot Server! This message lives comfortably in MongoDB Atlas.',
        'is_read' => false,
        'created_at' => now()->toISOString(),
        'updated_at' => now()->toISOString()
    ]);
    echo "SUCCESS: Test message securely inserted into MongoDB Atlas!\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
