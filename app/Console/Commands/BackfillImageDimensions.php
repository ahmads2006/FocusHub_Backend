<?php

namespace App\Console\Commands;

use App\Helpers\MediaHelper;
use App\Models\Image;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillImageDimensions extends Command
{
    protected $signature = 'gallery:backfill-dimensions
                            {--limit=200 : Max images to process per run}
                            {--force : Re-read dimensions even if already set}';

    protected $description = 'Backfill width/height in image_meta.technical_specs for gallery masonry frames';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');

        $images = Image::publicGallery()
            ->with(['meta', 'storage'])
            ->latest()
            ->limit($limit * 3)
            ->get()
            ->filter(function (Image $image) use ($force) {
                if ($force) {
                    return true;
                }
                $specs = MediaHelper::extractTechnicalSpecs($image->meta);
                return empty($specs['width']) || empty($specs['height']);
            })
            ->take($limit)
            ->values();

        if ($images->isEmpty()) {
            $this->info('No images need dimension backfill.');
            return self::SUCCESS;
        }

        $this->info("Processing {$images->count()} images...");
        $bar = $this->output->createProgressBar($images->count());
        $bar->start();

        $updated = 0;
        $skipped = 0;

        foreach ($images as $image) {
            $bar->advance();

            $specs = MediaHelper::extractTechnicalSpecs($image->meta);
            if (!$force && !empty($specs['width']) && !empty($specs['height'])) {
                $skipped++;
                continue;
            }

            $readable = $this->resolveReadablePath($image);
            if (!$readable) {
                $skipped++;
                continue;
            }

            $specs = MediaHelper::mergeDimensionsIntoSpecs($specs, $readable);

            if (empty($specs['width']) || empty($specs['height'])) {
                $skipped++;
                if (str_starts_with($readable, sys_get_temp_dir())) {
                    @unlink($readable);
                }
                continue;
            }

            $image->meta()->updateOrCreate(
                ['image_id' => $image->id],
                ['technical_specs' => $specs]
            );

            $updated++;

            if (str_starts_with($readable, sys_get_temp_dir())) {
                @unlink($readable);
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Updated: {$updated}, skipped: {$skipped}");

        return self::SUCCESS;
    }

    protected function resolveReadablePath(Image $image): ?string
    {
        $path = $image->storage?->path ?? $image->path;
        if (!$path) {
            return null;
        }

        foreach (['public', 'local', 's3'] as $disk) {
            try {
                if (!Storage::disk($disk)->exists($path)) {
                    continue;
                }
                if (in_array($disk, ['public', 'local'], true)) {
                    return Storage::disk($disk)->path($path);
                }
                $tmp = tempnam(sys_get_temp_dir(), 'opal_dims_');
                if ($tmp === false) {
                    return null;
                }
                file_put_contents($tmp, Storage::disk($disk)->get($path));
                return $tmp;
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
