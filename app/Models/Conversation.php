<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'name',
        'is_name_custom',
        'album_id',
        'created_by',
        'last_message_at',
    ];

    protected $casts = [
        'is_name_custom' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class, 'album_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
                    ->withPivot('role', 'joined_at', 'last_read_at')
                    ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    /**
     * Check if a user is a participant of this conversation.
     */
    public function hasParticipant(string $userId): bool
    {
        return $this->participants()->where('users.id', $userId)->exists();
    }

    /**
     * Get unread message count for a specific user.
     */
    public function unreadCountFor(string $userId): int
    {
        $participant = $this->participants()->where('users.id', $userId)->first();

        if (!$participant || !$participant->pivot->last_read_at) {
            return $this->messages()->where('sender_id', '!=', $userId)->count();
        }

        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('created_at', '>', $participant->pivot->last_read_at)
            ->count();
    }
}
