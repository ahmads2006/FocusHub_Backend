<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Authenticate as the user to bypass shadow scope issues
$user = App\Models\User::find('019e41ba-9b15-7322-94e5-3eaa8607a6be');
auth()->login($user);

echo "=== BEFORE FIX (old scope logic) ===\n";
$oldCount = App\Models\Image::where('privacy', 'public')
    ->where(function($q) {
        $q->whereNull('album_id')
          ->orWhereHas('album', function($aq) { $aq->public(); });
    })->count();
echo "Old publicGallery count: $oldCount\n";

echo "\n=== AFTER FIX (new scope logic) ===\n";
$newCount = App\Models\Image::publicGallery()->count();
echo "New publicGallery count: $newCount\n";

echo "\n=== DIFFERENCE ===\n";
echo "New images now visible: " . ($newCount - $oldCount) . "\n";

echo "\n=== Images that were HIDDEN before but now VISIBLE ===\n";
$ghostTitles = ['Quick Uploads', 'General Uploads'];
$nowVisible = App\Models\Image::where('privacy', 'public')
    ->whereHas('album', function($aq) use ($ghostTitles) {
        $aq->withoutGlobalScopes()->whereIn('title', $ghostTitles);
    })
    ->whereHas('album', function($aq) {
        // Album is NOT public (this is what was blocking them before)
        $aq->withoutGlobalScopes()->whereHas('settings', function($sq) {
            $sq->where('privacy', '!=', 'public');
        });
    })
    ->where('moderation_status', 'approved')
    ->count();
echo "Images in hidden Quick Uploads that are now PUBLIC: $nowVisible\n";

echo "\n=== All images by this user ===\n";
$allUserImages = $user->images()->withoutGlobalScopes()->get();
echo "Total: " . $allUserImages->count() . "\n";
echo "Public: " . $allUserImages->where('privacy', 'public')->count() . "\n";
echo "Private: " . $allUserImages->where('privacy', 'private')->count() . "\n";

echo "\n=== This user's PUBLIC images - Album status ===\n";
$userPublicImages = $user->images()->withoutGlobalScopes()->where('privacy', 'public')->get();
foreach($userPublicImages as $img) {
    $album = App\Models\Album::withoutGlobalScopes()->find($img->album_id);
    $settings = App\Models\AlbumSettings::where('album_id', $img->album_id)->first();
    $inGallery = App\Models\Image::publicGallery()->where('id', $img->id)->exists() ? 'YES' : 'NO';
    echo "  IMG: {$img->id} | Album: " . ($album->title ?? 'N/A') . " | AlbumSettings: " . ($settings->privacy ?? 'N/A') . " | InGallery: {$inGallery}\n";
}
