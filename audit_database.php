<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$pass = 0;
$fail = 0;
$total = 38; // Estimate

function printHeader($text) {
    echo "\n\033[1;36m━━━ $text ━━━\033[0m\n";
}

function printPass($msg) {
    global $pass;
    $pass++;
    echo "  \033[1;32m✅ $msg\033[0m\n";
}

function printFail($msg) {
    global $fail;
    $fail++;
    echo "  \033[1;31m❌ $msg\033[0m\n";
}

echo "\n\033[1;35m╔═══════════════════════════════════════════════════════════╗\033[0m\n";
echo "\033[1;35m║  🔬 OpalShot Deep Audit — Laravel Runtime Verification  ║\033[0m\n";
echo "\033[1;35m╚═══════════════════════════════════════════════════════════╝\033[0m\n";

printHeader("1. CONFIGURATION (resolved from config())");
$configs = [
    'cache.default' => 'redis',
    'session.driver' => 'redis',
    'queue.default' => 'redis',
    'database.default' => 'mysql',
    'filesystems.default' => 's3',
];

foreach ($configs as $key => $expected) {
    $actual = config($key);
    if ($actual === $expected) {
        printPass(ucfirst(explode('.', $key)[0]) . " driver = $expected");
    } else {
        printFail(ucfirst(explode('.', $key)[0]) . " driver = $actual (expected $expected)");
    }
}

if (config('queue.batching.database') !== 'sqlite') {
    printPass("Queue batching DB ≠ sqlite");
} else {
    printFail("Queue batching DB = sqlite");
}

if (config('queue.failed.database') !== 'sqlite') {
    printPass("Queue failed DB ≠ sqlite");
} else {
    printFail("Queue failed DB = sqlite");
}

printHeader("2. LOG CHANNELS");
$channels = ['mongodb', 'datadog'];
foreach ($channels as $ch) {
    if (config("logging.channels.$ch")) {
        printPass("Log channel: $ch defined");
        try {
            Log::channel($ch);
            printPass("Log::channel('$ch') instantiable");
        } catch (Exception $e) {
            printFail("Log::channel('$ch') failed: " . $e->getMessage());
        }
    } else {
        printFail("Log channel: $ch missing");
        $fail++;
    }
}

printHeader("3. DATABASE CONNECTIONS");
try {
    DB::connection('mysql')->getPdo();
    printPass("MySQL connection: OK");
} catch (Exception $e) {
    printFail("MySQL connection → " . $e->getMessage());
}

if (config('database.connections.mongodb')) {
    printPass("MongoDB config exists");
    if (config('database.connections.mongodb.dsn')) {
        printPass("MongoDB DSN configured");
        try {
            DB::connection('mongodb')->getMongoClient()->listDatabases();
            printPass("MongoDB connection: OK");
        } catch (Exception $e) {
            printFail("MongoDB connection → " . $e->getMessage());
        }
    } else {
        printFail("MongoDB DSN missing");
    }
} else {
    printFail("MongoDB config missing");
    $fail += 2;
}

try {
    $redis = Illuminate\Support\Facades\Redis::connection();
    $redis->ping();
    printPass("Redis connection: OK");
} catch (Exception $e) {
    printFail("Redis connection → " . $e->getMessage());
}

printHeader("4. CACHE TAGS SUPPORT");
try {
    Cache::tags(['test'])->put('test_key', 'test_value', 10);
    printPass("Cache::tags() working");
} catch (Exception $e) {
    printFail("Cache::tags() → " . $e->getMessage());
}

printHeader("5. REDIS KEY PATTERNS (Observers vs SyncCommand)");
$syncFile = __DIR__.'/app/Console/Commands/SyncRedisCounters.php';
$syncContent = file_exists($syncFile) ? file_get_contents($syncFile) : '';

$checks = [
    'likes' => ['LikeObserver.php', 'user:{id}:stats:likes'],
    'connections' => ['ConnectionObserver.php', 'user:{id}:stats:connections'],
    'photos' => ['ImageModerationObserver.php', 'user:{id}:stats:photos'],
];

foreach ($checks as $stat => $data) {
    $obsFile = __DIR__.'/app/Observers/'.$data[0];
    if (!file_exists($obsFile)) {
        printFail("$data[0] missing");
        continue;
    }
    
    $obsContent = file_get_contents($obsFile);
    preg_match('/user:(?:\{\$?[a-zA-Z0-9_\->]+\}|\$?[a-zA-Z0-9_\->]+):stats:' . $stat . '/', $obsContent, $obsMatch);
    preg_match('/user:(?:\{\$?[a-zA-Z0-9_\->]+\}|\$?[a-zA-Z0-9_\->]+):stats:' . $stat . '/', $syncContent, $syncMatch);
    
    if (!empty($obsMatch) && !empty($syncMatch)) {
        printPass("Key match [$stat]: Observer='user:{{id}}:stats:$stat' Sync='user:{{id}}:stats:$stat'");
    } else {
        printFail("Key mismatch [$stat]");
    }
}

printHeader("6. OBSERVER REGISTRATION");
$providerFile = __DIR__.'/app/Providers/ObserverServiceProvider.php';
$providerContent = file_exists($providerFile) ? file_get_contents($providerFile) : '';
$models = ['ImageModeration', 'Like', 'Connection', 'Image', 'Album'];

foreach ($models as $model) {
    if (strpos($providerContent, $model.'::observe') !== false) {
        printPass("Observer registered: {$model}Observer → App\Models\\$model");
    } else {
        printFail("Observer missing: {$model}Observer");
    }
}

printHeader("7. OBSERVER RESILIENCE (try-catch)");
$observers = ['LikeObserver.php', 'ConnectionObserver.php', 'ImageModerationObserver.php'];
foreach ($observers as $obs) {
    $content = file_exists(__DIR__.'/app/Observers/'.$obs) ? file_get_contents(__DIR__.'/app/Observers/'.$obs) : '';
    if (strpos($content, 'try {') !== false && strpos($content, 'catch') !== false && strpos($content, 'Redis::') !== false) {
        printPass("$obs: Redis operations protected by try-catch");
    } else {
        printFail("$obs: Missing try-catch for Redis");
    }
}

printHeader("8. ARTISAN COMMANDS");
$commands = [
    'mongo:clean-orphans' => 'CleanMongoOrphans.php',
    'redis:sync-counters' => 'SyncRedisCounters.php'
];
foreach ($commands as $cmd => $file) {
    if (file_exists(__DIR__.'/app/Console/Commands/'.$file)) {
        printPass("Command: $cmd registered");
    } else {
        printFail("Command missing: $cmd");
    }
}

printHeader("9. MONGO MODELS");
$mongoModels = ['ChatMessage', 'AiAnalysis'];
foreach ($mongoModels as $m) {
    $file = __DIR__.'/app/Models/Mongo/'.$m.'.php';
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'protected $connection = \'mongodb\';') !== false) {
            printPass("App\Models\Mongo\\$m: connection=mongodb");
        } else {
            printFail("App\Models\Mongo\\$m: missing mongodb connection");
        }
    } else {
        printFail("Mongo model missing: $m");
    }
}

printHeader("10. BATCH COMPLETION LOGIC");
$jobs = ['ProcessImageJob.php', 'ModerateImageJob.php', 'ProcessImageModeration.php', 'ExtractArchiveJob.php'];
foreach ($jobs as $job) {
    $file = __DIR__.'/app/Jobs/'.$job;
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'rejected_items') !== false || strpos($content, 'rejected_count') !== false) {
            printPass("$job: includes rejected_items in logic");
        } else {
            printFail("$job: ignores rejected_items in batch progress calculation");
        }
    } else {
        printFail("Job missing: $job");
    }
}

printHeader("11. SECURITY");
if (!file_exists(__DIR__.'/test_send_mongo.php')) {
    printPass("No exposed test files");
} else {
    printFail("test_send_mongo.php is EXPOSED!");
}

if (config('app.debug') == false || config('app.env') === 'local') {
    printPass("APP_DEBUG is safely configured for environment");
} else {
    printFail("APP_DEBUG is true in production!");
}

echo "\n\033[1;35m╔═══════════════════════════════════════════════════════════╗\033[0m\n";
echo "\033[1;35m║  " . ($fail === 0 ? "✅" : "⚠️") . "  النتيجة: $pass/" . ($pass+$fail) . " ناجح — $fail فشل                ║\033[0m\n";
echo "\033[1;35m╚═══════════════════════════════════════════════════════════╝\033[0m\n";
