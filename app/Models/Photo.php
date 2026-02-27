<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

class Photo extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, HasTags;

    protected $fillable = [
        'user_id',
        'album_id',
        'title',
        'description',
        'privacy',
        'exif_data',
        'is_comparison',
        'views_count',
        'downloads_count',
    ];

    protected $casts = [
        'exif_data' => 'array',
        'is_comparison' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(Analytics::class);
    }

    public function sharedLinks(): MorphMany
    {
        return $this->morphMany(SharedLink::class, 'shareable');
    }

    /**
     * Scope for independent photos (without an album).
     */
    public function scopeIndependent(Builder $query): Builder
    {
        return $query->whereNull('album_id');
    }
}
