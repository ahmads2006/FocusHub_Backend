<?php

namespace App\Jobs;

use App\Models\MongoDBOutbox;
use App\Services\Firebase\FirestoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessFirebaseOutbox implements ShouldQueue
{
    use Queueable;

    public $timeout = 60; // 1 minute per batch max

    public function handle(FirestoreService $firestore): void
    {
        Log::channel('single')->info('ProcessFirebaseOutbox started');

        $pending = MongoDBOutbox::whereIn('status', ['pending', 'retrying'])
                    ->where('attempts', '<', 5)
                    ->orderBy('created_at', 'asc')
                    ->take(100)
                    ->get();

        if ($pending->isEmpty()) {
            return;
        }

        foreach ($pending as $task) {
            try {
                if ($task->operation === 'insert' || $task->operation === 'set') {
                    $docId = $task->payload['id'] ?? (string) \Illuminate\Support\Str::uuid();
                    $firestore->setDocument($task->collection, $docId, $task->payload);
                } elseif ($task->operation === 'delete') {
                    $docId = $task->payload['id'] ?? null;
                    if ($docId) {
                        $firestore->deleteDocument($task->collection, $docId);
                    }
                }

                $task->delete();
            } catch (\Throwable $e) {
                $newAttempts = $task->attempts + 1;
                $status = $newAttempts >= 5 ? 'failed' : 'retrying';

                $task->update([
                    'status'     => $status,
                    'attempts'   => $newAttempts,
                    'last_error' => $e->getMessage(),
                ]);

                Log::channel('single')->error('Firebase Outbox task failed', [
                    'id'     => $task->id,
                    'err'    => $e->getMessage(),
                    'status' => $status,
                ]);
            }
        }
    }
}
