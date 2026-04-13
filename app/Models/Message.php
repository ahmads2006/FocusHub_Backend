<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Conversation;

class Message extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'conversation_id',
        'image_id',
        'album_id',
        'body',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    // ─── Relationships ───────────────────────────────────────

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class, 'album_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'image_id');
    }

    // ─── Scopes ──────────────────────────────────────────────

    /**
     * Get messages between two users (conversation).
     */
    public function scopeConversation(Builder $query, string $userId1, string $userId2): Builder
    {
        return $query->where(function ($q) use ($userId1, $userId2) {
            $q->where('sender_id', $userId1)->where('receiver_id', $userId2);
        })->orWhere(function ($q) use ($userId1, $userId2) {
            $q->where('sender_id', $userId2)->where('receiver_id', $userId1);
        });
    }

    /**
     * Get unread messages for a specific user.
     */
    public function scopeUnreadFor(Builder $query, string $userId): Builder
    {
        return $query->where('receiver_id', $userId)->where('is_read', false);
    }
}
