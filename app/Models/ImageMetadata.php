<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageMetadata extends Model
{
    protected $table = 'image_metadata';

    protected $fillable = [
        'image_id',
        'camera_make',
        'camera_model',
        'lens_type',
        'focal_length',
        'aperture',
        'shutter_speed',
        'iso',
        'original_creation_date',
        'extra_info',
    ];

    protected $casts = [
        'extra_info' => 'array',
        'original_creation_date' => 'datetime',
    ];

    public function image()
    {
        return $this->belongsTo(Image::class);
    }
}
