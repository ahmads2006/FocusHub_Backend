<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeImageLabelsJob;
use App\Models\Image;
use App\Services\Core\ImageService;
use Illuminate\Console\Command;

class AnalyzeExistingImages extends Command
{
    protected $signature = 'images:analyze
                            {--limit=50 : Maximum number of images to queue per run}
                            {--queue=default : Queue to dispatch on}';

    protected $description = 'Dispatch AI classification jobs for existing images that have no labels yet';

    public function handle(ImageService $imageService): int
    {
        $limit = (int) $this->option('limit');
        $queue = $this->option('queue');

        $images = Image::withoutGlobalScopes()->where(function ($q) {
                $q->whereNull('labels')
                  ->orWhereJsonLength('labels', 0);
            })
            ->whereDoesntHave('moderation', function ($q) {
                $q->where('status', 'rejected');
            })
            ->limit($limit)
            ->get();

        if ($images->isEmpty()) {
            $this->info('✅ All images already have labels. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("🧠 Processing {$images->count()} images (moderation backfill first)...");
        $bar = $this->output->createProgressBar($images->count());
        $bar->start();

        $queued = 0;
        foreach ($images as $index => $image) {
            $image->loadMissing('moderation');
            if ($imageService->persistLabelsFromModeration($image)) {
                $bar->advance();
                continue;
            }

            // Stagger dispatches by 2s to avoid quota hammering on external APIs
            AnalyzeImageLabelsJob::dispatch($image->id)
                ->onQueue($queue)
                ->delay(now()->addSeconds($index * 2));

            $queued++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $backfilled = $images->count() - $queued;
        $this->info("✅ Done! {$backfilled} backfilled from moderation, {$queued} jobs queued. Run `php artisan queue:work` if needed.");

        return self::SUCCESS;
    }
}
