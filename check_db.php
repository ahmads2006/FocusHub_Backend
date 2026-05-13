<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SharedLink;

echo "Checking last 10 shared links:\n";
echo str_repeat("-", 80) . "\n";
foreach(SharedLink::orderBy("created_at", "desc")->limit(10)->get() as $l) {
    echo "ID: {$l->id}\n";
    echo "Session: " . ($l->session_id ?: 'NULL') . "\n";
    echo "Access: {$l->access_count} / " . ($l->max_access ?: 'INF') . "\n";
    echo "Active: " . ($l->is_active ? 'YES' : 'NO') . "\n";
    echo "Created: {$l->created_at}\n";
    echo str_repeat("-", 80) . "\n";
}
