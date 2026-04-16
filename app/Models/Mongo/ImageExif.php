<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model as MongoDBModel;

class ImageExif extends MongoDBModel
{
    protected $connection = 'mongodb';
    protected $collection = 'image_exifs';

    protected $fillable = [
        'id',               // manual UUID
        'image_id',         // plain string (UUID)
        'camera_make',
        'camera_model',
        'lens',
        'focal_length',
        'aperture',
        'shutter_speed',
        'iso',
        'gps',              // GeoJSON or standard coords format
        'raw_exif',         // Complete raw JSON from parsing EXIF
    ];

    protected $casts = [
        'gps' => 'array',
        'raw_exif' => 'array',
    ];
}
