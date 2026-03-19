<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeImageLabelsJob;
use App\Models\Image;
use Illuminate\Console\Command;

class AnalyzeExistingImages extends Command
{
    protected $signature = 'images:analyze
                            {--limit=50 : Maximum number of images to queue per run}
                            {--queue=default : Queue to dispatch on}';

    protected $description = 'Dispatch AI classification jobs for existing images that have no labels yet';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $queue = $this->option('queue');

        $images = Image::where(function ($q) {
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

        $this->info("🧠 Queuing AI analysis for {$images->count()} images...");
        $bar = $this->output->createProgressBar($images->count());
        $bar->start();

        foreach ($images as $index => $image) {
            // Stagger dispatches by 2s to avoid quota hammering on external APIs
            AnalyzeImageLabelsJob::dispatch($image->id)
                ->onQueue($queue)
                ->delay(now()->addSeconds($index * 2));

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Done! {$images->count()} jobs dispatched. Run `php artisan queue:work` to process them.");

        return self::SUCCESS;
    }
}
