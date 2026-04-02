<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'admin_id', 'status'];

    // ─── Relations ──────────────────────────────
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'conversation_id');
    }

    // ─── Scopes ──────────────────────────────
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForAdmin($query, string $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    // ─── Helpers ──────────────────────────────
    public function isClaimed(): bool
    {
        return $this->admin_id !== null;
    }

    public function isClaimedBy(string $adminId): bool
    {
        return $this->admin_id === $adminId;
    }
}
