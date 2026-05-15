<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing Cache (Redis)...\n";
$key = "test_key_" . time();
\Illuminate\Support\Facades\Cache::put($key, "WORKING", 60);

if (\Illuminate\Support\Facades\Cache::get($key) === "WORKING") {
    echo "✅ Cache is WORKING!\n";
} else {
    echo "❌ Cache is FAILED!\n";
}

echo "Testing Direct Redis...\n";
try {
    \Illuminate\Support\Facades\Redis::set("direct_test", "OK");
    if (\Illuminate\Support\Facades\Redis::get("direct_test") === "OK") {
        echo "✅ Direct Redis is WORKING!\n";
    } else {
        echo "❌ Direct Redis returned wrong value!\n";
    }
} catch (\Exception $e) {
    echo "❌ Redis Connection ERROR: " . $e->getMessage() . "\n";
}

echo "Current Prefix: " . \Illuminate\Support\Facades\Config::get('cache.prefix') . "\n";
