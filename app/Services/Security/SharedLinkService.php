<?php

namespace App\Services\Security;

use App\Models\SharedLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SharedLinkService
{
    /**
     * Generate a secure shared link for a model (Album or Photo/Image).
     */
    public function generate(Model $model, ?Carbon $expiry = null, ?string $password = null, ?int $maxAccess = null, string $permission = 'view', bool $autoRotate = true, ?bool $requireWatermark = null, ?string $label = null): SharedLink
    {
        // Generate a random token (simple and clean)
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        $data = [
            'shareable_id' => $model->id,
            'shareable_type' => get_class($model),
            'token' => $token,
            'label' => $label,
            'password' => $password ? Hash::make($password) : null,
            'permission' => $permission,
            'expires_at' => $expiry,
            'max_access' => $maxAccess,
            'auto_rotate'        => $autoRotate,
            'require_watermark'  => $requireWatermark,
        ];

        // 🛡️ Create a model instance to handle encryption and attributes consistently
        $link = new SharedLink($data);

        // If the link is temporary (has expiry), store it ONLY in Redis
        if ($expiry) {
            $ttl = now()->diffInSeconds($expiry);
            if ($ttl > 0) {
                // Important: SharedLink model casts 'token' to 'encrypted'. 
                // We need the raw token to be returned to the user, but stored encrypted in cache.
                $link->token = $token; 
                
                // Store the encrypted attributes in Redis
                \Illuminate\Support\Facades\Cache::put("ephemeral_link:{$tokenHash}", $link->getAttributes(), $ttl);
                
                $link->exists = true;
                return $link;
            }
        }

        $link->save();
        return $link;
    }


    /**
     * Get the full access URL for a shared link.
     */
    public function getFullUrl(SharedLink $link): string
    {
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', url('/')));
        $frontendUrl = rtrim($frontendUrl, '/');
        
        return "{$frontendUrl}/s/{$link->token}";
    }
}
