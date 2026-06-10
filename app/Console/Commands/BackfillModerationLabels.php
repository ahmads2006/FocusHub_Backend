<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeImageLabelsJob;
use App\Models\Image;
use App\Services\Core\ImageService;
use Illuminate\Console\Command;

class BackfillModerationLabels extends Command
{
    protected $signature = 'images:backfill-moderation-labels
                            {--limit=200 : Max images to process}
                            {--sync : Run AI tagging synchronously (slow, for local debug)}';

    protected $description = 'Copy AI tags from moderation metadata into labels for images missing tags (e.g. yellow-zone uploads)';

    public function handle(ImageService $imageService): int
    {
        $limit = (int) $this->option('limit');

        $images = Image::withoutGlobalScopes()
            ->with('moderation')
            ->where(function ($q) {
                $q->whereNull('labels')->orWhereJsonLength('labels', 0);
            })
            ->whereHas('moderation', function ($q) {
                $q->whereNotIn('status', ['rejected']);
            })
            ->limit($limit)
            ->get();

        if ($images->isEmpty()) {
            $this->info('No images need moderation-label backfill.');
            return self::SUCCESS;
        }

        $updated = 0;
        $queued = 0;
        foreach ($images as $image) {
            if ($imageService->persistLabelsFromModeration($image)) {
                $updated++;
                continue;
            }

            if ($this->option('sync')) {
                AnalyzeImageLabelsJob::dispatchSync($image->id);
                $image->refresh();
                if (!empty($image->labels)) {
                    $updated++;
                } else {
                    $queued++;
                }
            } else {
                AnalyzeImageLabelsJob::dispatch($image->id)->onQueue('default');
                $queued++;
            }
        }

        $this->info("Labels restored for {$updated} / {$images->count()} images ({$queued} still pending AI).");

        return self::SUCCESS;
    }
}
