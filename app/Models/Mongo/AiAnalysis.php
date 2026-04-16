<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model as MongoDBModel;

class AiAnalysis extends MongoDBModel
{
    protected $connection = 'mongodb';
    protected $collection = 'ai_analysis';

    protected $fillable = [
        'id',               // manual UUID
        'image_id',         // plain string (UUID)
        'provider',         // e.g. "aws_rekognition", "google_vision"
        'labels',           // Array of strings
        'objects_detected', // Array of complex objects
        'colors',           // Hex arrays
        'faces',            // Complex nested data
        'scene',            // Text
        'confidence_scores',// Key-value map
        'raw_response',     // The entire unfiltered JSON from provider
    ];

    protected $casts = [
        'labels' => 'array',
        'objects_detected' => 'array',
        'colors' => 'array',
        'faces' => 'array',
        'confidence_scores' => 'array',
        'raw_response' => 'array',
    ];
}
