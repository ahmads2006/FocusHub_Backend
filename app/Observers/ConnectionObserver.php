<?php

namespace App\Observers;

use App\Models\Connection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ConnectionObserver
{
    public function updated(Connection $connection)
    {
        try {
            if ($connection->isDirty('status')) {
                if ($connection->status === 'accepted') {
                    Redis::incr("user:{$connection->user_id}:stats:connections");
                    Redis::incr("user:{$connection->connected_user_id}:stats:connections");
                } elseif ($connection->getOriginal('status') === 'accepted') {
                    Redis::decr("user:{$connection->user_id}:stats:connections");
                    Redis::decr("user:{$connection->connected_user_id}:stats:connections");
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ConnectionObserver Redis updated failed', ['error' => $e->getMessage()]);
        }
    }

    public function deleted(Connection $connection)
    {
        try {
            if ($connection->status === 'accepted') {
                Redis::decr("user:{$connection->user_id}:stats:connections");
                Redis::decr("user:{$connection->connected_user_id}:stats:connections");
            }
        } catch (\Throwable $e) {
            Log::warning('ConnectionObserver Redis deleted failed', ['error' => $e->getMessage()]);
        }
    }
}
