<?php

namespace App\Models;

use App\Models\Scopes\ShadowPrivacyScope;
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

    const STATUS_PENDING       = 'pending';
    const STATUS_APPROVED      = 'approved';
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_REJECTED      = 'rejected';
    const STATUS_UNDER_REVIEW  = 'under_review';

    protected static function booted(): void
    {
        static::addGlobalScope(new ShadowPrivacyScope);
    }

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
        'file_type',
        'path',
        'size',
        'privacy',
        'exif_data',
        'metadata',
        'is_comparison',
        'views_count',
        'downloads_count',
        'copyright_enabled',
        'allow_download',
        'watermark_on_download',
        'technical_specs',
        'specs',
        'imagekit_file_id',
        'imagekit_file_path',
        'status',
        'ai_metadata',
    ];

    protected $casts = [
        'exif_data' => 'json',
        'metadata' => 'encrypted:array',
        'technical_specs' => 'json',
        'specs' => 'json',
        'is_comparison' => 'boolean',
        'copyright_enabled' => 'boolean',
        'allow_download' => 'boolean',
        'watermark_on_download' => 'boolean',
        'ai_metadata' => 'json',
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

    public function reports(): HasMany
    {
        return $this->hasMany(ImageReport::class);
    }

    // Status Helpers
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPendingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Accessor for the image URL.
     * Default to 'Medium' (800px) version for performance and protection.
     */
    public function getUrlAttribute(): string
    {
        return app(\App\Services\Core\AssetDeliveryService::class)->getUrl($this, 'gallery');
    }

    /**
     * Get a secure, masked URL for the original high-res file.
     */
    public function getOriginalUrl(): string
    {
        return app(\App\Services\Core\AssetDeliveryService::class)->getUrl($this, 'original');
    }

    /**
     * Get the URL for a specific thumbnail size.
     */
    public function getThumbnailUrl(string $size = 'medium'): string
    {
        try {
            $thumbnails = $this->metadata['thumbnails'] ?? [];
        } catch (\Exception $e) {
            $thumbnails = [];
        }

        $path = $thumbnails[$size] ?? $this->path;

        return asset('storage/' . ltrim($path, '/'));
    }
}
