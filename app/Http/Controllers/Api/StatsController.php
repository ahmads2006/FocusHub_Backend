<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * Get the user's storage drive statistics.
     * 10GB Limit System v23.0
     */
    public function driveStats(Request $request)
    {
        $user = $request->user();
        
        // Calculate the total number of images to provide context
        $imageCount = $user->images()->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'storage_used_bytes' => (int) $user->storage_used_bytes,
                'storage_limit_bytes' => (int) $user->storage_limit_bytes,
                'storage_remaining_bytes' => (int) $user->getStorageRemainingBytesAttribute(),
                'used_percentage' => (float) $user->getStorageUsedPercentageAttribute(),
                'image_count' => $imageCount,
                'is_unlimited' => $user->hasRole('super-admin') || !$user->storage_limit_bytes,
                'readable' => [
                    'used' => $this->formatBytes($user->storage_used_bytes),
                    'limit' => $user->storage_limit_bytes ? $this->formatBytes($user->storage_limit_bytes) : 'Infinite',
                    'remaining' => $user->storage_limit_bytes ? $this->formatBytes($user->getStorageRemainingBytesAttribute()) : 'Infinite'
                ]
            ]
        ]);
    }

    /**
     * Helper to format bytes into human-readable strings.
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
