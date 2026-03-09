<?php

namespace App\Jobs;

use App\Models\Image;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use App\Services\SmartCompressionService;

class ProcessImageThumbnails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $image;

    /**
     * Create a new job instance.
     */
    public function __construct(Image $image)
    {
        $this->image = $image;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $sourcePath = Storage::disk('public')->path($this->image->path);
        
        if (!file_exists($sourcePath)) {
            return;
        }

        // 1. Initialize Imagick Manager for professional-grade control
        $manager = new ImageManager(new Driver());
        $image = $manager->read($sourcePath);
        
        $optimizer = app(SmartCompressionService::class);
        $originalSize = $this->image->size;

        $thumbnailsDir = "photos/{$this->image->id}/thumbnails";
        Storage::disk('public')->makeDirectory($thumbnailsDir);

        $variants = [
            'large'  => ['width' => 1200, 'height' => null, 'crop' => false],
            'medium' => ['width' => 800,  'height' => null, 'crop' => false],
            'square' => ['width' => 200,  'height' => 200,  'crop' => true],
            'avatar' => ['width' => 150,  'height' => 150,  'crop' => true],
        ];

        $thumbnailPaths = [];

        foreach ($variants as $size => $config) {
            $variantImage = clone $image;
            $filename = "{$size}.webp";
            $path = "{$thumbnailsDir}/{$filename}";
            $absolutePath = Storage::disk('public')->path($path);

            // 2. Apply Resizing
            if ($config['crop']) {
                $variantImage->cover($config['width'], $config['height']);
            } else {
                $variantImage->scaleDown(width: $config['width'], height: $config['height']);
            }

            // 3. Smart Adaptive Compression
            $settings = $optimizer->getOptimizationSettings($variantImage, $originalSize, $size);
            
            // Access raw core for deep Imagick tuning (Chroma Subsampling, Stripping)
            $optimizer->tuneImagick($variantImage->core()->native());

            $variantImage->toWebp(quality: $settings['quality'])->save($absolutePath);
            $thumbnailPaths[$size] = $path;
        }

        // Update image metadata with thumbnail paths
        $metadata = $this->image->metadata ?? [];
        $metadata['thumbnails'] = $thumbnailPaths;
        
        $this->image->update([
            'metadata' => $metadata
        ]);
    }
}
