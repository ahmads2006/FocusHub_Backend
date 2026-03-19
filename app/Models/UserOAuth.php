<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOAuth extends Model
{
    protected $table = 'user_oauth';

    protected $fillable = [
        'user_id',
        'google_id',
        'provider_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
