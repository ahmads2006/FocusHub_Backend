<?php

namespace App\Models;

use App\Models\Scopes\ShadowPrivacyScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\ImageAppeal;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Image extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, HasTags, LogsActivity;

    // Keep legacy constants for backward compatibility (now in ImageModeration)
    const STATUS_PENDING        = 'pending';
    const STATUS_APPROVED       = 'approved';
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_REJECTED       = 'rejected';
    const STATUS_UNDER_REVIEW   = 'under_review';

    protected static function booted(): void
    {
        static::addGlobalScope(new ShadowPrivacyScope);

        // Filter by moderation visibility via the related table
        static::addGlobalScope('visible', function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->whereHas('moderation', fn($q) => $q->where('is_visible', true));
        });

        // Auto-create related rows on creation
        static::created(function (Image $image) {
            ImageStorage::create(['image_id' => $image->id]);
            ImageMeta::create(['image_id' => $image->id]);
            ImageModeration::create(['image_id' => $image->id, 'status' => self::STATUS_APPROVED]);
            ImageSettings::create(['image_id' => $image->id]);
        });
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
        'size',
        'privacy',
    ];

    protected $casts = [];

    // ───────────────────────── Relations ─────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /** Paths & cloud storage */
    public function storage(): HasOne
    {
        return $this->hasOne(ImageStorage::class);
    }

    /** EXIF & technical specs */
    public function meta(): HasOne
    {
        return $this->hasOne(ImageMeta::class);
    }

    /** AI moderation & status */
    public function moderation(): HasOne
    {
        return $this->hasOne(ImageModeration::class);
    }

    /** Permissions & counters */
    public function settings(): HasOne
    {
        return $this->hasOne(ImageSettings::class);
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

    public function appeals(): HasMany
    {
        return $this->hasMany(ImageAppeal::class);
    }

    /** AI Analysis Metadata (polymorphic) */
    public function aiMetadata(): HasOne
    {
        return $this->hasOne(MediaAiMetadata::class, 'media_id');
    }

    // ───────────────────────── Proxy Accessors (backward compat) ─────────────────────────

    /** @deprecated Use $image->moderation->status */
    public function getStatusAttribute(): ?string
    {
        return $this->moderation?->status;
    }

    /** @deprecated Use $image->moderation->is_sensitive */
    public function getIsSensitiveAttribute(): bool
    {
        return (bool) ($this->moderation?->is_sensitive ?? false);
    }

    /** Returns the comma-separated sensitivity reason tags stored by ContentSafetyService */
    public function getSensitivityReasonAttribute(): ?string
    {
        return $this->moderation?->sensitivity_reason;
    }

    /** @deprecated Use $image->moderation->is_visible */
    public function getIsVisibleAttribute(): bool
    {
        return (bool) ($this->moderation?->is_visible ?? true);
    }

    /** @deprecated Use $image->storage->path */
    public function getPathAttribute(): ?string
    {
        return $this->storage?->path;
    }

    /** @deprecated Use $image->storage->imagekit_file_path */
    public function getImagekitFilePathAttribute(): ?string
    {
        return $this->storage?->imagekit_file_path;
    }

    /** @deprecated Use $image->storage->imagekit_file_id */
    public function getImagekitFileIdAttribute(): ?string
    {
        return $this->storage?->imagekit_file_id;
    }

    /** @deprecated Use $image->settings->allow_download */
    public function getAllowDownloadAttribute(): bool
    {
        return (bool) ($this->settings?->allow_download ?? true);
    }

    /**
     * Get the name of the AI driver that analyzed this image.
     */
    public function getAnalyzerNameAttribute(): string
    {
        return $this->aiMetadata?->driver_name ?? 'N/A';
    }

    // ───────────────────────── Status Helpers ─────────────────────────

    public function isApproved(): bool { return $this->status === self::STATUS_APPROVED; }
    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
    public function isPendingReview(): bool { return $this->status === self::STATUS_PENDING_REVIEW; }
    public function isRejected(): bool { return $this->status === self::STATUS_REJECTED; }

    // ───────────────────────── URL Helpers ─────────────────────────

    /**
     * Accessor for the image display URL.
     */
    public function getUrlAttribute(): string
    {
        return app(\App\Services\Core\AssetDeliveryService::class)->getUrl($this, 'gallery');
    }

    /**
     * Get a secure URL for the original high-res file.
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
            $thumbnails = $this->meta?->metadata['thumbnails'] ?? [];
        } catch (\Exception $e) {
            $thumbnails = [];
        }

        $path = $thumbnails[$size] ?? $this->path;
        return asset('storage/' . ltrim($path, '/'));
    }
}
