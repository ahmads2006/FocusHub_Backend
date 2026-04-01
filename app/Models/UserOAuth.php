<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOAuth extends Model
{
    use HasUuids;

    protected $table = 'user_oauth';

    protected $fillable = [
        'user_id',
        'google_id',
        'adobe_id',
        'provider_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
