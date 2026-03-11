<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class QuarantinePrune extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quarantine:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup orphaned files in the secure quarantine zone older than 24 hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->comment('🔍 Scanning quarantine zone for expired assets...');
        
        $files = \Illuminate\Support\Facades\Storage::disk('local')->files('quarantine');
        $count = 0;
        $now = time();

        foreach ($files as $file) {
            // Keep .gitignore
            if (basename($file) === '.gitignore') continue;

            $lastModified = \Illuminate\Support\Facades\Storage::disk('local')->lastModified($file);
            
            // If file is older than 24 hours (86400 seconds)
            if (($now - $lastModified) > 86400) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($file);
                $count++;
            }
        }

        if ($count > 0) {
            $this->info("✅ Pruned {$count} orphaned files from quarantine.");
        } else {
            $this->line('   No expired assets found in quarantine.');
        }

        \Illuminate\Support\Facades\Log::info("Quarantine Prune: Deleted {$count} files.");
    }
}
