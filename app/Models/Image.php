<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Image extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, HasTags, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'user_id',
        'album_id',
        'title',
        'description',
        'filename',
        'path',
        'size',
        'privacy',
        'exif_data',
        'is_comparison',
        'views_count',
        'downloads_count',
        'copyright_enabled'
    ];

    protected $casts = [
        'exif_data' => 'json',
        'is_comparison' => 'boolean',
        'copyright_enabled' => 'boolean',
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
     * Accessor for the image URL, handling CDN (TwicPics) architecture.
     */
    public function getUrlAttribute(): string
    {
        $path = $this->path;
        
        if (config('services.twicpics.domain')) {
            return config('services.twicpics.domain') . '/' . ltrim($path, '/');
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}
