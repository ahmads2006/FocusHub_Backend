<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'watermark_text',
        'dynamic_watermark',
        'watermark_text_color',
        'watermark_neon_color',
        'watermark_opacity',
        'watermark_logo',
        'use_text_watermark',
        'use_logo_watermark',
        'auto_orient_default',
        'stay_logged_in',
        'is_public_profile',
    ];

    protected $casts = [
        'dynamic_watermark' => 'boolean',
        'watermark_opacity' => 'float',
        'use_text_watermark' => 'boolean',
        'use_logo_watermark' => 'boolean',
        'auto_orient_default' => 'boolean',
        'stay_logged_in' => 'boolean',
        'is_public_profile' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
