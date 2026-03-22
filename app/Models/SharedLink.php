<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SharedLink extends Model
{
    use HasUuids;

    protected $fillable = [
        'shareable_id',
        'shareable_type',
        'token',
        'password',
        'session_id',
        'auto_rotate',
        'permission',
        'expires_at',
        'access_count',
        'max_access',
        'last_accessed_at',
        'revoked_at',
        'require_watermark',
    ];

    protected $appends = ['is_active', 'status_text'];

    protected $hidden = [
        'password',
    ];

    protected static function booted()
    {
        static::saving(function ($link) {
            if ($link->isDirty('token')) {
                $link->token_hash = hash('sha256', $link->token);
            }
        });
    }

    protected $casts = [
        'token' => 'encrypted',
        'session_id' => 'encrypted',
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'access_count' => 'integer',
        'max_access' => 'integer',
        'auto_rotate' => 'boolean',
        'require_watermark' => 'boolean',
    ];

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isLimitReached(): bool
    {
        return $this->max_access && $this->access_count >= $this->max_access;
    }

    /**
     * Frontend-aware status flags
     */
    public function getIsActiveAttribute(): bool
    {
        return !$this->isExpired() && !$this->isRevoked() && !$this->isLimitReached();
    }

    public function getStatusTextAttribute(): string
    {
        if ($this->isRevoked()) {
            return 'Revoked';
        }
        if ($this->isExpired()) {
            return 'Expired';
        }
        if ($this->isLimitReached()) {
            return 'Limit Reached';
        }
        return 'Active';
    }
}
