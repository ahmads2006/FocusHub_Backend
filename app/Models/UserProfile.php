<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'bio',
        'profile_picture',
        'avatar',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
