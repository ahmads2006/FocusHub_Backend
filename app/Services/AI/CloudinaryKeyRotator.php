<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CloudinaryKeyRotator — Multi-Account Quota Manager
 * 
 * Manages a pool of Cloudinary accounts and provides intelligent key rotation
 * to maximize the free-tier quota (50 auto-tags/month per account).
 * 
 * Strategy:
 *   1. Load all configured accounts from config('services.cloudinary.accounts').
 *   2. Skip any account whose key has been marked as "exhausted" in cache.
 *   3. Return the first available account credentials.
 *   4. When a 429 (quota exceeded) is detected, mark that account as exhausted
 *      for 30 days (the Cloudinary billing cycle) via Redis/Cache.
 *   5. If ALL accounts are exhausted, return null so the caller can gracefully
 *      degrade (upload without tags).
 * 
 * Cache Keys:
 *   - "cloudinary:exhausted:{api_key}" => true (TTL: 30 days)
 *   - "cloudinary:usage:{api_key}"     => int  (usage counter for monitoring)
 * 
 * Performance:
 *   - Zero API calls to check availability — purely cache-based.
 *   - O(n) where n = number of accounts (typically 3-10, negligible).
 */
class CloudinaryKeyRotator
{
    /**
     * Cooldown period in days before a key is re-checked.
     * Cloudinary's free tier resets monthly.
     */
    protected const COOLDOWN_DAYS = 30;

    /**
     * Cache prefix for exhausted keys.
     */
    protected const CACHE_PREFIX_EXHAUSTED = 'cloudinary:exhausted:';

    /**
     * Cache prefix for usage counters (monitoring only).
     */
    protected const CACHE_PREFIX_USAGE = 'cloudinary:usage:';

    /**
     * Get all configured Cloudinary accounts.
     *
     * @return array<int, array{cloud_name: string, api_key: string, api_secret: string}>
     */
    public function getAccounts(): array
    {
        $accountsJson = config('services.cloudinary.accounts');

        // Parse JSON string from config (sourced from .env)
        if (is_string($accountsJson)) {
            $accounts = json_decode($accountsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('CloudinaryKeyRotator: Failed to parse CLOUDINARY_ACCOUNTS JSON: ' . json_last_error_msg());
                $accounts = [];
            }
        } elseif (is_array($accountsJson)) {
            $accounts = $accountsJson;
        } else {
            $accounts = [];
        }

        // Backward compatibility: If no accounts array is defined, fall back to the
        // single legacy credentials from CLOUDINARY_CLOUD_NAME / API_KEY / API_SECRET
        if (empty($accounts)) {
            $legacyCloudName = config('services.cloudinary.cloud_name');
            $legacyApiKey = config('services.cloudinary.api_key');
            $legacyApiSecret = config('services.cloudinary.api_secret');

            if ($legacyCloudName && $legacyApiKey && $legacyApiSecret) {
                $accounts = [[
                    'cloud_name' => $legacyCloudName,
                    'api_key'    => $legacyApiKey,
                    'api_secret' => $legacyApiSecret,
                ]];
            }
        }

        return $accounts;
    }

    /**
     * Get the next available (non-exhausted) account credentials.
     *
     * @return array{cloud_name: string, api_key: string, api_secret: string}|null
     *         Returns null if ALL accounts are exhausted.
     */
    public function getAvailableCredentials(): ?array
    {
        $accounts = $this->getAccounts();

        if (empty($accounts)) {
            Log::warning('CloudinaryKeyRotator: No Cloudinary accounts configured.');
            return null;
        }

        foreach ($accounts as $index => $account) {
            $apiKey = $account['api_key'] ?? '';
            $cacheKey = self::CACHE_PREFIX_EXHAUSTED . $apiKey;

            if (Cache::has($cacheKey)) {
                $expiresAt = Cache::get($cacheKey . ':expires_at', 'unknown');
                Log::debug("CloudinaryKeyRotator: Skipping account #{$index} (key: ...{$this->maskKey($apiKey)}) — exhausted until {$expiresAt}");
                continue;
            }

            Log::info("CloudinaryKeyRotator: Using account #{$index} (key: ...{$this->maskKey($apiKey)})");
            return $account;
        }

        Log::warning('CloudinaryKeyRotator: ALL Cloudinary accounts are exhausted. Tagging will be skipped.');
        return null;
    }

    /**
     * Mark an account's API key as exhausted (quota exceeded).
     * The key won't be retried for 30 days to avoid unnecessary API calls
     * and latency on every upload.
     *
     * @param string $apiKey The API key that hit its quota limit.
     */
    public function markExhausted(string $apiKey): void
    {
        $cacheKey = self::CACHE_PREFIX_EXHAUSTED . $apiKey;
        $ttl = now()->addDays(self::COOLDOWN_DAYS);

        Cache::put($cacheKey, true, $ttl);
        Cache::put($cacheKey . ':expires_at', $ttl->toDateTimeString(), $ttl);

        // Increment the exhaustion event counter for monitoring/alerting
        $monthKey = self::CACHE_PREFIX_USAGE . 'exhaustions:' . now()->format('Y-m');
        Cache::increment($monthKey);

        Log::warning("CloudinaryKeyRotator: Marked key ...{$this->maskKey($apiKey)} as exhausted until {$ttl->toDateTimeString()}");
    }

    /**
     * Manually reset an exhausted key (e.g., after upgrading or when a new billing cycle starts).
     *
     * @param string $apiKey The API key to reactivate.
     */
    public function resetKey(string $apiKey): void
    {
        $cacheKey = self::CACHE_PREFIX_EXHAUSTED . $apiKey;
        Cache::forget($cacheKey);
        Cache::forget($cacheKey . ':expires_at');

        Log::info("CloudinaryKeyRotator: Manually reset key ...{$this->maskKey($apiKey)}");
    }

    /**
     * Reset ALL exhausted keys (nuclear option for testing or billing resets).
     */
    public function resetAllKeys(): void
    {
        $accounts = $this->getAccounts();

        foreach ($accounts as $account) {
            $apiKey = $account['api_key'] ?? '';
            if ($apiKey) {
                $this->resetKey($apiKey);
            }
        }

        Log::info('CloudinaryKeyRotator: All keys have been reset.');
    }

    /**
     * Get a status report of all accounts.
     *
     * @return array<int, array{index: int, api_key_masked: string, status: string, expires_at: string|null}>
     */
    public function getStatus(): array
    {
        $accounts = $this->getAccounts();
        $report = [];

        foreach ($accounts as $index => $account) {
            $apiKey = $account['api_key'] ?? '';
            $cacheKey = self::CACHE_PREFIX_EXHAUSTED . $apiKey;
            $isExhausted = Cache::has($cacheKey);

            $report[] = [
                'index'          => $index,
                'cloud_name'     => $account['cloud_name'] ?? 'unknown',
                'api_key_masked' => $this->maskKey($apiKey),
                'status'         => $isExhausted ? 'exhausted' : 'available',
                'expires_at'     => $isExhausted ? Cache::get($cacheKey . ':expires_at') : null,
            ];
        }

        return $report;
    }

    /**
     * Mask an API key for safe logging (show only last 4 characters).
     */
    protected function maskKey(string $key): string
    {
        if (strlen($key) <= 4) {
            return '****';
        }
        return str_repeat('*', strlen($key) - 4) . substr($key, -4);
    }
}
