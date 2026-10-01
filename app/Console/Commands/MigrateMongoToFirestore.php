<?php

namespace App\Console\Commands;

use App\Services\Firebase\FirestoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateMongoToFirestore extends Command
{
    protected $signature = 'firestore:migrate-from-mongo {--collection= : Specific collection to migrate}';
    protected $description = 'Migrate data from MongoDB collections to Google Cloud Firestore';

    protected array $collections = [
        'chat_messages',
        'activity_logs',
        'image_exif',
        'ai_analyses',
    ];

    public function handle(FirestoreService $firestore): int
    {
        $targetCollection = $this->option('collection');
        $collectionsToMigrate = $targetCollection ? [$targetCollection] : $this->collections;

        $this->info("Starting migration from MongoDB to Firestore...");
        $this->newLine();

        foreach ($collectionsToMigrate as $collection) {
            $this->info(" Migrating collection: [{$collection}]");

            try {
                $count = DB::connection('mongodb')->table($collection)->count();

                if ($count === 0) {
                    $this->line("   └─ No records found in MongoDB for [{$collection}].");
                    continue;
                }

                $this->line("   └─ Found {$count} documents to migrate.");
                $bar = $this->output->createProgressBar($count);
                $bar->start();

                DB::connection('mongodb')->table($collection)->chunk(100, function ($records) use ($firestore, $collection, $bar) {
                    foreach ($records as $record) {
                        $data = (array) $record;
                        $docId = (string) ($data['id'] ?? $data['_id'] ?? \Illuminate\Support\Str::uuid());
                        $data['id'] = $docId;
                        $data['_id'] = $docId;

                        $firestore->setDocument($collection, $docId, $data);
                        $bar->advance();
                    }
                });

                $bar->finish();
                $this->newLine();
                $this->info("   └─ Successfully migrated [{$collection}]!");
            } catch (\Throwable $e) {
                $this->warn("   └─ Could not migrate [{$collection}]: " . $e->getMessage());
            }

            $this->newLine();
        }

        $this->info(" Firestore migration process completed!");
        return self::SUCCESS;
    }
}
