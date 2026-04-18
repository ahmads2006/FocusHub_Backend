<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class CleanMongoOrphans extends Command
{
    protected $signature = 'mongo:clean-orphans {--dry-run : Preview deletions without executing}';
    protected $description = 'Clean up orphaned MongoDB documents where the MySQL parent was deleted';

    /**
     * Collections to clean and the MySQL column they reference.
     */
    protected array $collections = [
        'chat_messages' => ['foreign_keys' => ['sender_id', 'receiver_id'], 'model' => User::class],
        'activity_logs' => ['foreign_keys' => ['user_id'], 'model' => User::class],
        'ai_analysis'   => ['foreign_keys' => ['image_id'], 'model' => \App\Models\Image::class],
        'image_exifs'   => ['foreign_keys' => ['image_id'], 'model' => \App\Models\Image::class],
    ];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $totalOrphans = 0;

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE — لن يتم حذف أي شيء.');
        }

        $this->info('Starting MongoDB orphan cleanup...');
        $this->newLine();

        foreach ($this->collections as $collectionName => $config) {
            $this->info("━━━ Scanning: {$collectionName} ━━━");

            try {
                $mongoCollection = DB::connection('mongodb')->table($collectionName);
                $orphanCount = 0;

                foreach ($config['foreign_keys'] as $foreignKey) {
                    // Get all unique referenced IDs from MongoDB
                    $referencedIds = $mongoCollection
                        ->whereNotNull($foreignKey)
                        ->pluck($foreignKey)
                        ->unique()
                        ->values()
                        ->toArray();

                    if (empty($referencedIds)) {
                        $this->line("  ├─ {$foreignKey}: No documents found.");
                        continue;
                    }

                    // Find which IDs still exist in MySQL
                    $existingIds = $config['model']::withoutGlobalScopes()
                        ->whereIn('id', $referencedIds)
                        ->pluck('id')
                        ->toArray();

                    // Calculate orphans
                    $orphanIds = array_diff($referencedIds, $existingIds);
                    $count = count($orphanIds);

                    if ($count === 0) {
                        $this->line("  ├─ {$foreignKey}: ✅ No orphans found ({$count}/" . count($referencedIds) . " valid).");
                        continue;
                    }

                    $orphanCount += $count;
                    $this->warn("  ├─ {$foreignKey}: ⚠️  Found {$count} orphaned documents.");

                    if (!$isDryRun) {
                        $deleted = $mongoCollection
                            ->whereIn($foreignKey, $orphanIds)
                            ->delete();

                        $this->info("  │  └─ Deleted {$deleted} documents.");
                    } else {
                        $this->line("  │  └─ Would delete {$count} documents (dry-run).");
                    }
                }

                $totalOrphans += $orphanCount;

                if ($orphanCount === 0) {
                    $this->info("  └─ ✅ Collection is clean.");
                }
            } catch (\Exception $e) {
                $this->error("  └─ ❌ Error scanning {$collectionName}: " . $e->getMessage());
            }

            $this->newLine();
        }

        $this->newLine();
        if ($totalOrphans === 0) {
            $this->info('🎉 All MongoDB collections are clean. No orphans found.');
        } else {
            $action = $isDryRun ? 'found (not deleted — dry-run)' : 'cleaned';
            $this->warn("📊 Total orphaned documents {$action}: {$totalOrphans}");
        }

        return self::SUCCESS;
    }
}
