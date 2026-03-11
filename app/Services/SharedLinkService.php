<?php

namespace App\Services;

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
    public function generate(Model $model, ?Carbon $expiry = null, ?string $password = null, ?int $maxAccess = null, string $permission = 'view', bool $autoRotate = false, ?bool $requireWatermark = null): SharedLink
    {
        $token = Str::random(64);

        return SharedLink::create([
            'shareable_id' => $model->id,
            'shareable_type' => get_class($model),
            'token' => $token,
            'password' => $password ? Hash::make($password) : null,
            'permission' => $permission,
            'expires_at' => $expiry,
            'max_access' => $maxAccess,
            'auto_rotate'        => $autoRotate,
            'require_watermark'  => $requireWatermark,
        ]);
    }

    /**
     * Get the full access URL for a shared link.
     */
    public function getFullUrl(SharedLink $link): string
    {
        return url("/s/{$link->token}");
    }
}
