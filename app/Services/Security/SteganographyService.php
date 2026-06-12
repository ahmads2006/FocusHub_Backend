<?php

namespace App\Services\Security;

use App\Models\Image;
use Illuminate\Support\Facades\Log;

class SteganographyService
{
    /**
     * Embed the steganographic signature into the file.
     */
    public function embed(Image $image, string $filePath): bool
    {
        try {
            if (!class_exists(\Imagick::class)) {
                Log::warning("SteganographyService: Imagick extension is not installed. Skipping steganographic embedding.");
                return false;
            }

            if (!file_exists($filePath) || !is_writable($filePath)) {
                Log::warning("SteganographyService: File does not exist or is not writable: {$filePath}");
                return false;
            }

            // Generate secure token signature
            $signature = $this->generateSignature($image);

            $imagick = new \Imagick($filePath);
            
            // Set 'comment' property
            $imagick->setImageProperty('comment', 'steg_sig:' . $signature);
            
            // Save the image
            $imagick->writeImage($filePath);
            $imagick->clear();
            $imagick->destroy();

            Log::info("SteganographyService: Successfully embedded signature into image {$image->id}");
            return true;
        } catch (\Exception $e) {
            Log::error("SteganographyService: Failed to embed signature: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract and verify signature from the file.
     * Returns array with image_id and user_id if successful, null otherwise.
     */
    public function verify(string $filePath): ?array
    {
        try {
            if (!class_exists(\Imagick::class)) {
                Log::warning("SteganographyService: Imagick extension is not installed. Cannot verify signature.");
                return null;
            }

            if (!file_exists($filePath) || !is_readable($filePath)) {
                Log::warning("SteganographyService: File does not exist or is not readable: {$filePath}");
                return null;
            }

            $imagick = new \Imagick($filePath);
            $comment = $imagick->getImageProperty('comment');
            $imagick->clear();
            $imagick->destroy();

            if (empty($comment) || !str_starts_with($comment, 'steg_sig:')) {
                return null;
            }

            $signature = substr($comment, 9);
            return $this->decodeAndVerifySignature($signature);
        } catch (\Exception $e) {
            Log::error("SteganographyService: Verification failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate the cryptographic signature.
     */
    protected function generateSignature(Image $image): string
    {
        $payload = [
            'id' => $image->id,
            'user_id' => $image->user_id,
            'ts' => time(),
        ];

        $jsonPayload = json_encode($payload);
        $encodedPayload = base64_encode($jsonPayload);
        $hash = hash_hmac('sha256', $encodedPayload, config('app.key'));

        return $encodedPayload . '.' . $hash;
    }

    /**
     * Decode and verify signature.
     */
    protected function decodeAndVerifySignature(string $signature): ?array
    {
        $parts = explode('.', $signature);
        if (count($parts) !== 2) {
            return null;
        }

        $encodedPayload = $parts[0];
        $hash = $parts[1];

        // Verify HMAC
        $expectedHash = hash_hmac('sha256', $encodedPayload, config('app.key'));
        if (!hash_equals($expectedHash, $hash)) {
            return null;
        }

        $jsonPayload = base64_decode($encodedPayload);
        $payload = json_decode($jsonPayload, true);

        if (!is_array($payload) || !isset($payload['id'], $payload['user_id'])) {
            return null;
        }

        return $payload;
    }
}
