<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MediaAiMetadata extends Model
{
    protected $table = 'media_ai_metadata';

    protected $fillable = [
        'media_id',
        'media_type',
        'driver_name',
        'raw_results',
        'extracted_tags',
        'quality_grade',
        'category',
        'ocr_text',
        'is_sensitive',
        'confidence_score',
    ];

    protected $casts = [
        'raw_results' => 'array',
        'extracted_tags' => 'array',
        'is_sensitive' => 'boolean',
        'confidence_score' => 'float',
    ];

    /**
     * Get the parent media model (Image, etc.).
     */
    public function media(): MorphTo
    {
        return $this->morphTo();
    }
}
