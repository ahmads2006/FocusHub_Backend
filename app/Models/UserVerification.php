<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVerification extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'verification_code',
        'is_verified',
        'last_login_ip',
        'device_token',
        'device_trusted_until',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'device_trusted_until' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
