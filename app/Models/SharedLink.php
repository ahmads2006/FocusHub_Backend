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
        'expires_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }
}
