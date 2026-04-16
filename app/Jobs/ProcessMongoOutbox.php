<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Models\MongoDBOutbox;

class ProcessMongoOutbox implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    public $timeout = 60; // 1 minute per batch max

    public function handle(): void
    {
        Log::channel('mongodb')->info('ProcessMongoOutbox started');

        // Target up to 100 pending messages per run to prevent timeout/memory issues
        $pending = MongoDBOutbox::where('status', 'pending')
                    ->orderBy('created_at', 'asc')
                    ->take(100)
                    ->get();
                    
        if ($pending->isEmpty()) {
            return;
        }

        foreach ($pending as $task) {
            try {
                if ($task->operation === 'insert') {
                    \Illuminate\Support\Facades\DB::connection('mongodb')
                        ->table($task->collection)
                        ->insert($task->payload);
                } 
                // Add updates/deletes here later if needed

                // Delete upon overwhelming success
                $task->delete();

            } catch (\Exception $e) {
                $task->update([
                    'status' => 'failed',
                    'attempts' => $task->attempts + 1,
                    'last_error' => $e->getMessage(),
                ]);
                Log::channel('mongodb')->error('Outbox failed', ['id' => $task->id, 'err' => $e->getMessage()]);
            }
        }
    }
}
