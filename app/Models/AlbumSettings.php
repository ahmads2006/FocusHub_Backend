<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlbumSettings extends Model
{
    use HasUuids;

    protected $table = 'album_settings';

    protected $fillable = [
        'album_id',
        'privacy',
        'is_collaborative',
        'cover_image',
        'status',
    ];

    protected $casts = [
        'is_collaborative' => 'boolean',
    ];

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }
}
