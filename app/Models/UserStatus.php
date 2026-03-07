<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStatus extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'is_banned',
        'banned_at',
        'is_shadow_hidden',
        'is_deleted',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_banned' => 'boolean',
            'banned_at' => 'datetime',
            'is_shadow_hidden' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
