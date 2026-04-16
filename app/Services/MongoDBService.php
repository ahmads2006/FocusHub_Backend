<?php

namespace App\Services;

use App\Models\MongoDBOutbox;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MongoDBService
{
    /**
     * Use Outbox pattern to safely queue MongoDB insertions inside MySQL transactions.
     * The worker will actually insert it later natively.
     */
    public function outboxInsert(string $collection, array $payload): void
    {
        // Enforce UUID generation for the MongoDB document ahead of time
        // so MySQL and the frontend immediately know the ID of the document.
        if (!isset($payload['id'])) {
            $payload['id'] = (string) Str::uuid();
        }
        if (!isset($payload['_id'])) {
            $payload['_id'] = $payload['id'];
        }

        MongoDBOutbox::create([
            'collection' => $collection,
            'operation'  => 'insert',
            'payload'    => $payload,
            'status'     => 'pending',
        ]);

        \App\Jobs\ProcessMongoOutbox::dispatch();
    }

    /**
     * Fallback for direct inserts if testing or necessary, 
     * but normally we rely on the Outbox pattern.
     */
    public function directInsert(string $collection, array $payload): void
    {
        try {
            // Since we're using Jenssegers/Laravel-MongoDB, we can use standard Eloquent/Query builder
            \Illuminate\Support\Facades\DB::connection('mongodb')
                ->table($collection)
                ->insert($payload);
        } catch (\Exception $e) {
            Log::channel('mongodb')->error('MongoDB Direct Insert failed (Circuit Breaker OPEN)', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            
            // Fallback to outbox seamlessly
            $this->outboxInsert($collection, $payload);
        }
    }
}
