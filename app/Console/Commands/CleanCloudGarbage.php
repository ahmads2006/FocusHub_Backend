<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CleanCloudGarbage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'opticvault:clean-garbage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up temporary files and old quarantined images to free up space.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Garbage Collection...');
        $deletedTempFiles = 0;
        $deletedQuarantineFiles = 0;

        // 1. Clean up local temp files older than 24 hours
        $localDisk = Storage::disk('local');
        $files = $localDisk->files('');
        foreach ($files as $file) {
            if (str_starts_with($file, 'temp_')) {
                $lastModified = $localDisk->lastModified($file);
                if (Carbon::createFromTimestamp($lastModified)->diffInHours(now()) > 24) {
                    $localDisk->delete($file);
                    $deletedTempFiles++;
                }
            }
        }

        // 2. Clean up quarantined images older than 30 days
        $directories = $localDisk->allDirectories('quarantine');
        foreach ($directories as $dir) {
            $quarantineFiles = $localDisk->files($dir);
            foreach ($quarantineFiles as $file) {
                $lastModified = $localDisk->lastModified($file);
                if (Carbon::createFromTimestamp($lastModified)->diffInDays(now()) > 30) {
                    $localDisk->delete($file);
                    $deletedQuarantineFiles++;
                }
            }
        }

        Log::info("Garbage Collection Completed: Deleted $deletedTempFiles temp files and $deletedQuarantineFiles quarantined files.");
        $this->info("Successfully deleted $deletedTempFiles temp files and $deletedQuarantineFiles quarantined files.");
    }
}
