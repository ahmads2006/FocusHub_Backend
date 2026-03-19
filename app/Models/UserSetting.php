<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'dynamic_watermark',
        'watermark_text_color',
        'watermark_neon_color',
        'watermark_opacity',
        'auto_orient_default',
        'stay_logged_in',
    ];

    protected $casts = [
        'dynamic_watermark' => 'boolean',
        'watermark_opacity' => 'float',
        'auto_orient_default' => 'boolean',
        'stay_logged_in' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
