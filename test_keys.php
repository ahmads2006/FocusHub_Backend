<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use ImageKit\ImageKit;

echo "\n🔍 Testing AI & Cloud SDK Keys...\n";
echo str_repeat("=", 50) . "\n";

// 1. Google Vision API
echo "1. Google Vision API: ";
$gvKey = config('services.google.vision_api_key');
if (empty($gvKey)) {
    echo "❌ Missing in .env\n";
} else {
    $res = Http::post("https://vision.googleapis.com/v1/images:annotate?key={$gvKey}", [
        'requests' => [
            ['image' => ['content' => base64_encode(random_bytes(10))], 'features' => [['type' => 'LABEL_DETECTION']]]
        ]
    ]);
    if ($res->successful() || $res->status() === 400) { // 400 means Bad Request (invalid image), but KEY is valid!
        echo "✅ Connected Successfully!\n";
    } else {
        echo "❌ Failed (" . $res->status() . ") - " . ($res->json()['error']['message'] ?? '') . "\n";
    }
}

// 2. Cloudinary API
echo "2. Cloudinary API: ";
$cName = config('services.cloudinary.cloud_name');
$cKey = config('services.cloudinary.api_key');
$cSecret = config('services.cloudinary.api_secret');
if (empty($cName) || empty($cKey) || empty($cSecret)) {
    echo "❌ Missing credentials in .env\n";
} else {
    $res = Http::withBasicAuth($cKey, $cSecret)
        ->get("https://api.cloudinary.com/v1_1/{$cName}/ping");
    if ($res->successful()) {
        echo "✅ Connected Successfully!\n";
    } else {
        echo "❌ Failed (" . $res->status() . ") - " . ($res->json()['error']['message'] ?? '') . "\n";
    }
}

// 3. Sightengine API
echo "3. Sightengine API: ";
$seUser = config('services.sightengine.api_user');
$seSecret = config('services.sightengine.api_secret');
if (empty($seUser) || empty($seSecret)) {
    echo "❌ Missing credentials in .env\n";
} else {
    $res = Http::get('https://api.sightengine.com/1.0/check.json', [
        'api_user' => $seUser,
        'api_secret' => $seSecret,
        'url' => 'https://sightengine.com/assets/img/examples/example5.jpg',
        'models' => 'nudity-2.1',
    ]);
    if ($res->successful() && ($res['status'] ?? '') === 'success') {
        echo "✅ Connected Successfully!\n";
    } else {
        echo "❌ Failed (" . $res->status() . ") - " . ($res->json()['error']['message'] ?? '') . "\n";
    }
}

// 4. ImageKit API
echo "4. ImageKit API: ";
$ikPublic = config('services.imagekit.public_key');
$ikPrivate = config('services.imagekit.private_key');
$ikUrl = config('services.imagekit.url_endpoint');
if (empty($ikPublic) || empty($ikPrivate) || empty($ikUrl)) {
    echo "❌ Missing credentials in .env\n";
} else {
    try {
        $ik = new ImageKit($ikPublic, $ikPrivate, $ikUrl);
        $res = $ik->listFiles(['limit' => 1]);
        if (isset($res->error)) {
            echo "❌ Failed - " . json_encode($res->error) . "\n";
        } else {
            echo "✅ Connected Successfully!\n";
        }
    } catch (\Exception $e) {
        echo "❌ Failed Exception - " . $e->getMessage() . "\n";
    }
}

echo str_repeat("=", 50) . "\n\n";
