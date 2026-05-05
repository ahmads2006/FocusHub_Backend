<?php

namespace App\Console\Commands;

use App\Models\SupportMessage;
use App\Models\SupportConversation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneSupportMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'support:prune {--days=30 : The number of days of history to retain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically delete old support chat messages to keep the database clean';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->warn("🧹 Starting support chat cleanup (deleting messages older than {$days} days)...");

        // 1. Delete old messages
        $deletedMessagesCount = SupportMessage::where('created_at', '<', $cutoffDate)->delete();

        // 2. Delete conversations that have no messages and are closed
        $deletedConversationsCount = SupportConversation::where('status', 'closed')
            ->whereDoesntHave('messages')
            ->where('updated_at', '<', $cutoffDate)
            ->delete();

        $this->info("✅ Cleanup Complete:");
        $this->line("   - Messages Deleted: {$deletedMessagesCount}");
        $this->line("   - Empty Closed Conversations Removed: {$deletedConversationsCount}");

        Log::info("Support Pruning: Deleted {$deletedMessagesCount} messages and {$deletedConversationsCount} empty conversations.");

        return self::SUCCESS;
    }
}
