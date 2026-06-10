<?php

namespace App\Console\Commands;

use App\Services\AI\CloudinaryKeyRotator;
use Illuminate\Console\Command;

/**
 * Artisan command to inspect and manage Cloudinary multi-account key rotation.
 *
 * Usage:
 *   php artisan cloudinary:keys              — Show status of all accounts
 *   php artisan cloudinary:keys --reset      — Reset ALL exhausted keys
 *   php artisan cloudinary:keys --reset=KEY  — Reset a specific API key
 */
class CloudinaryKeyStatus extends Command
{
    protected $signature = 'cloudinary:keys {--reset= : Reset a specific API key or pass "all" to reset all keys}';
    protected $description = 'Show status of Cloudinary multi-account key rotation pool';

    public function handle(): int
    {
        $rotator = new CloudinaryKeyRotator();

        // Handle reset operations
        $resetTarget = $this->option('reset');
        if ($resetTarget !== null) {
            if ($resetTarget === 'all' || $resetTarget === '') {
                $rotator->resetAllKeys();
                $this->info('✅ All Cloudinary keys have been reset.');
            } else {
                $rotator->resetKey($resetTarget);
                $this->info("✅ Key ...{$this->maskKey($resetTarget)} has been reset.");
            }
            $this->newLine();
        }

        // Display status table
        $status = $rotator->getStatus();

        if (empty($status)) {
            $this->warn('⚠️ No Cloudinary accounts configured.');
            $this->line('Set CLOUDINARY_ACCOUNTS in your .env file as a JSON array.');
            return 1;
        }

        $this->info("🔑 Cloudinary Multi-Account Pool Status");
        $this->newLine();

        $rows = [];
        foreach ($status as $account) {
            $rows[] = [
                '#' . $account['index'],
                $account['cloud_name'],
                $account['api_key_masked'],
                $account['status'] === 'available'
                    ? '<fg=green>✅ Available</>'
                    : '<fg=red>❌ Exhausted</>',
                $account['expires_at'] ?? '—',
            ];
        }

        $this->table(
            ['#', 'Cloud Name', 'API Key', 'Status', 'Resets At'],
            $rows
        );

        $availableCount = count(array_filter($status, fn($a) => $a['status'] === 'available'));
        $totalCount = count($status);

        $this->newLine();
        $this->line("📊 {$availableCount}/{$totalCount} accounts available");

        if ($availableCount === 0) {
            $this->warn('⚠️ All accounts are exhausted! Images will be uploaded without tags.');
        }

        return 0;
    }

    protected function maskKey(string $key): string
    {
        if (strlen($key) <= 4) return '****';
        return str_repeat('*', strlen($key) - 4) . substr($key, -4);
    }
}
