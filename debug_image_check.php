<?php
// Temporary debug script to test image lookup
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->boot();

// Get the most recent image
$latestImage = \Illuminate\Support\Facades\DB::table('images')->orderBy('created_at', 'desc')->first();

if (!$latestImage) {
    echo "NO IMAGES IN DB\n";
    exit;
}

echo "=== Latest image from raw DB ===\n";
echo "ID: {$latestImage->id}\n";
echo "user_id: {$latestImage->user_id}\n";
echo "is_visible: " . var_export($latestImage->is_visible, true) . "\n";
echo "privacy: {$latestImage->privacy}\n";
echo "created_at: {$latestImage->created_at}\n\n";

// Test with Eloquent (WITH global scopes)
echo "=== Image::find() (WITH scopes) ===\n";
$withScopes = \App\Models\Image::find($latestImage->id);
echo "Result: " . ($withScopes ? "FOUND (user_id={$withScopes->user_id})" : "NULL - FILTERED BY GLOBAL SCOPES") . "\n\n";

// Test with Eloquent (WITHOUT global scopes)
echo "=== Image::withoutGlobalScopes()->find() ===\n";
$withoutScopes = \App\Models\Image::withoutGlobalScopes()->find($latestImage->id);
echo "Result: " . ($withoutScopes ? "FOUND (user_id={$withoutScopes->user_id})" : "NULL") . "\n\n";

// Test the exists validation
echo "=== Testing exists:images,id validation ===\n";
$validator = \Illuminate\Support\Facades\Validator::make(
    ['image_id' => $latestImage->id],
    ['image_id' => 'exists:images,id']
);
echo "Validation result: " . ($validator->fails() ? "FAILS - " . json_encode($validator->errors()->toArray()) : "PASSES") . "\n\n";

// Check moderation
echo "=== Moderation record ===\n";
$mod = \Illuminate\Support\Facades\DB::table('image_moderations')->where('image_id', $latestImage->id)->first();
if ($mod) {
    echo "status: {$mod->status}\n";
    echo "is_visible: " . var_export($mod->is_visible, true) . "\n";
} else {
    echo "NO moderation record\n";
}
