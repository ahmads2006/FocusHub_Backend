<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model as MongoDBModel;

class ActivityLog extends MongoDBModel
{
    protected $connection = 'mongodb';
    protected $collection = 'activity_logs';

    // Disable traditional created_at / updated_at in favor of manual or single field if needed,
    // though leaving it true gives free updated_at which is fine.
    public $timestamps = true;

    protected $fillable = [
        'id',            // manual UUID 
        'user_id',       // plain string (UUID)
        'action',        // e.g. "image_uploaded"
        'subject_type',  // e.g. "Image"
        'subject_id',    // plain string (UUID)
        'properties',    // Array of arbitrary data
        'ip_address',
        'user_agent',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
