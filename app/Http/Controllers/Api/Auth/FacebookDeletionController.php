<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacebookDeletionController extends Controller
{
    /**
     * Handle the Facebook Data Deletion Callback.
     * 
     * Facebook sends a POST request with a 'signed_request' parameter.
     */
    public function handle(Request $request)
    {
        $signed_request = $request->input('signed_request');

        if (!$signed_request) {
            return response()->json(['error' => 'Missing signed_request'], 400);
        }

        try {
            $data = $this->parseSignedRequest($signed_request);
            $user_facebook_id = $data['user_id'] ?? null;

            if ($user_facebook_id) {
                Log::info("Facebook Data Deletion Requested for User ID: {$user_facebook_id}");
                
                // Logic to delete or de-identify the user in your database
                // Example: User::where('social_id', $user_facebook_id)->delete();
            }

            // Facebook requires a confirmation code and a URL where the user can check status
            $confirmation_code = 'DEL_' . bin2hex(random_bytes(8));
            $status_url = config('app.frontend_url') . "/deletion-status?code=" . $confirmation_code;

            return response()->json([
                'url' => $status_url,
                'confirmation_code' => $confirmation_code
            ]);

        } catch (\Exception $e) {
            Log::error("Facebook Deletion Error: " . $e->getMessage());
            return response()->json(['error' => 'Invalid signed_request'], 400);
        }
    }

    /**
     * Parse the signed request from Facebook.
     */
    private function parseSignedRequest($signed_request)
    {
        list($encoded_sig, $payload) = explode('.', $signed_request, 2);

        $secret = config('services.facebook.client_secret') ?? config('services.instagram.client_secret');

        // Decode the data
        $sig = $this->base64UrlDecode($encoded_sig);
        $data = json_decode($this->base64UrlDecode($payload), true);

        // Confirm the signature
        $expected_sig = hash_hmac('sha256', $payload, $secret, $raw = true);
        if ($sig !== $expected_sig) {
            throw new \Exception('Bad Signed JSON signature!');
        }

        return $data;
    }

    private function base64UrlDecode($input)
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
