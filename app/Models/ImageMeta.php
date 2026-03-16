<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageMeta extends Model
{
    use HasUuids;

    protected $table = 'image_meta';

    protected $fillable = [
        'image_id',
        'exif_data',
        'technical_specs',
        'specs',
        'metadata',
    ];

    protected $casts = [
        'exif_data'       => 'json',
        'technical_specs' => 'json',
        'specs'           => 'json',
        'metadata'        => 'encrypted:array',
    ];

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }
}
