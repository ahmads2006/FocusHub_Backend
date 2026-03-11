<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OpticVaultInit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'optic:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize the OpticVault secure infrastructure (Directories, Symlinks, Dependencies)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Initializing OpticVault Secure Infrastructure...');

        // 1. Storage Structure
        $this->newLine();
        $this->comment('1. Checking File Structure...');
        
        $directories = [
            'quarantine',
            'images',
            'avatars',
        ];

        foreach ($directories as $dir) {
            if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($dir)) {
                \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory($dir);
                $this->line("   [CREATED] storage/app/{$dir}");
            } else {
                $this->line("   [EXISTS]  storage/app/{$dir}");
            }
        }

        // Add .gitignore to quarantine
        $quarantinePath = storage_path('app/quarantine');
        if (!file_exists($quarantinePath . '/.gitignore')) {
            file_put_contents($quarantinePath . '/.gitignore', "*\n!.gitignore");
            $this->line('   [ADDED]   .gitignore to quarantine');
        }

        // 2. Symlinks
        $this->newLine();
        $this->comment('2. Verifying Symlinks...');
        if (!file_exists(public_path('storage'))) {
            $this->call('storage:link');
        } else {
            $this->line('   [LINKED]  public/storage is active');
        }

        // 3. System Dependencies
        $this->newLine();
        $this->comment('3. Checking System Dependencies...');
        
        $extensions = ['gd', 'imagick', 'exif', 'json', 'openssl'];
        foreach ($extensions as $ext) {
            if (extension_loaded($ext)) {
                $this->line("   [OK] PHP Extension: {$ext}");
            } else {
                $this->error("   [MISSING] PHP Extension: {$ext}");
            }
        }

        $this->newLine();
        $this->info('✨ OpticVault Infrastructure is ready for secure operations.');
    }
}
