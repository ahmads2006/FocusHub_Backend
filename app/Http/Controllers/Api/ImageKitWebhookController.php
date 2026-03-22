<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImageKitWebhookController extends Controller
{
    /**
     * Handle incoming webhooks from ImageKit.
     */
    public function handle(Request $request)
    {
        // 1. Verify Signature
        if (!$this->verifySignature($request)) {
            Log::warning("ImageKit Webhook: Invalid signature detected.", ['ip' => $request->ip()]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $eventType = $payload['type'] ?? 'unknown';

        Log::info("ImageKit Webhook Received: {$eventType}", ['payload' => $payload]);

        // 2. Process Event
        try {
            switch ($eventType) {
                // Handle different ImageKit events here
                // Examples:
                // 'video.transformation.ready'
                // 'video.transformation.error'
                
                default:
                    Log::info("ImageKit Webhook: Unhandled event type: {$eventType}");
                    break;
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error("ImageKit Webhook Processing Error: " . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Verify the webhook signature against the configured secret.
     */
    protected function verifySignature(Request $request): bool
    {
        $signatureHeader = $request->header('X-Imagekit-Signature');
        
        if (!$signatureHeader) {
            return false;
        }

        $secret = config('services.imagekit.webhook_secret');
        
        if (empty($secret)) {
            Log::error("ImageKit Webhook: Secret not configured in environment.");
            // Fail closed securely
            return false;
        }

        $rawBody = $request->getContent();
        
        // ImageKit's docs say the signature is the HMAC hex digest of the raw body
        $expectedSignature = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expectedSignature, $signatureHeader);
    }
}
