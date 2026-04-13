<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SystemHealthController extends Controller
{
    /**
     * Get a summary of the system health, including queue status and recent logs.
     */
    public function index()
    {
        $health = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'logs' => $this->getRecentLogs(),
        ];

        return response()->json($health);
    }

    private function checkDatabase()
    {
        try {
            DB::connection()->getPdo();
            return 'OK';
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    private function checkRedis()
    {
        try {
            Redis::ping();
            return 'OK';
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    private function checkQueue()
    {
        try {
            $failedJobs = DB::table('failed_jobs')->latest('failed_at')->limit(5)->get()->map(function($job) {
                return [
                    'id' => $job->id,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'failed_at' => $job->failed_at,
                    'exception' => substr($job->exception, 0, 500) . '...',
                ];
            });

            return [
                'status' => 'OK',
                'failed_count' => DB::table('failed_jobs')->count(),
                'recent_failed' => $failedJobs
            ];
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    private function getRecentLogs()
    {
        $logPath = storage_path('logs/laravel.log');
        if (!file_exists($logPath)) {
            return 'Log file not found.';
        }

        // Get last 50 lines efficiently to prevent memory exhaustion on large log files
        $lines = [];
        exec('tail -n 50 ' . escapeshellarg($logPath), $lines);
        
        return array_map('trim', $lines);
    }
}
