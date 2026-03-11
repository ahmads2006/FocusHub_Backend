<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtectedImage extends Model
{
    protected $fillable = [
        'image_id',
        'hash',
        'path',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }
}
