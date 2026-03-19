<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageLabel extends Model
{
    protected $fillable = [
        'image_id',
        'labels',
    ];

    protected $casts = [
        'labels' => 'array',
    ];

    /**
     * Get the image that owns the labels.
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }
}
