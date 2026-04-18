<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SyncRedisCounters extends Command
{
    protected $signature = 'redis:sync-counters {--user= : Sync counters for a specific user ID}';
    protected $description = 'Rebuild Redis counters from MySQL to fix drift or after Redis restart';

    public function handle(): int
    {
        $this->info('🔄 Syncing Redis counters from MySQL...');
        $this->newLine();

        $specificUser = $this->option('user');

        // ── 1. User Stats Counters ──────────────────────────
        $this->info('━━━ User Stats ━━━');

        $usersQuery = User::withoutGlobalScopes();
        if ($specificUser) {
            $usersQuery->where('id', $specificUser);
        }

        $bar = $this->output->createProgressBar($usersQuery->count());
        $bar->start();

        $usersQuery->chunk(100, function ($users) use ($bar) {
            foreach ($users as $user) {
                // Key format: user:{id}:stats:likes (matches LikeObserver)
                $likesReceived = DB::table('image_likes')
                    ->join('images', 'image_likes.image_id', '=', 'images.id')
                    ->where('images.user_id', $user->id)
                    ->count();
                Redis::set("user:{$user->id}:stats:likes", $likesReceived);

                // Key format: user:{id}:stats:photos (matches ImageModerationObserver)
                $approvedPhotos = Image::withoutGlobalScopes()
                    ->where('user_id', $user->id)
                    ->where('privacy', 'public')
                    ->whereHas('moderation', fn($q) => $q->where('status', 'approved'))
                    ->count();
                Redis::set("user:{$user->id}:stats:photos", $approvedPhotos);

                // Key format: user:{id}:stats:connections (matches ConnectionObserver)
                $connectionsCount = DB::table('connections')
                    ->where(function ($q) use ($user) {
                        $q->where('user_id', $user->id)
                          ->orWhere('connected_user_id', $user->id);
                    })
                    ->where('status', 'accepted')
                    ->count();
                Redis::set("user:{$user->id}:stats:connections", $connectionsCount);

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        // ── 2. Trending Images Sorted Set ──────────────────────────
        $this->info('━━━ Trending Images (24h) ━━━');

        $recentLikes = DB::table('image_likes')
            ->where('created_at', '>=', now()->subDay())
            ->select('image_id', DB::raw('COUNT(*) as score'))
            ->groupBy('image_id')
            ->get();

        if ($recentLikes->isNotEmpty()) {
            Redis::del('trending_images_24h');
            foreach ($recentLikes as $item) {
                Redis::zadd('trending_images_24h', $item->score, $item->image_id);
            }
            $this->info("  ✅ Rebuilt trending set with {$recentLikes->count()} images.");
        } else {
            $this->info("  ℹ️  No recent likes in the last 24 hours.");
        }

        $this->newLine();
        $this->info('🎉 Redis counters synced successfully.');

        return self::SUCCESS;
    }
}
