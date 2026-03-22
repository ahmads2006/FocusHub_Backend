<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageStorage extends Model
{
    use HasUuids;

    protected $table = 'image_storage';

    protected $fillable = [
        'image_id',
        'path',
        'original_path',
        'imagekit_file_id',
        'imagekit_file_path',
        'enhanced_url',
        'md5_hash',
    ];

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }
}
