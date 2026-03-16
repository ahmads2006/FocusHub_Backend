<?php

namespace App\Models;

use App\Models\Scopes\ShadowPrivacyScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Album extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, LogsActivity;

    protected $pendingSettings = [];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShadowPrivacyScope);

        // Ensure related settings row exists and sync pending values
        static::saved(function (Album $album) {
            $settings = $album->pendingSettings;
            if (empty($settings) && !$album->settings()->exists()) {
                $settings['privacy'] = 'public';
                $settings['status'] = 'approved';
            }

            if (!empty($settings)) {
                // Ensure status is always set for new settings if not provided
                if (!isset($settings['status']) && !$album->settings()->exists()) {
                    $settings['status'] = 'approved';
                }
                
                $album->settings()->updateOrCreate(['album_id' => $album->id], $settings);
                $album->pendingSettings = []; // Clear after sync
            }
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
        'title',
        'description',
        'privacy',           // Kept for mass-assignment proxying
        'is_collaborative', // Kept for mass-assignment proxying
        'cover_image',      // Kept for mass-assignment proxying
        'status',           // Kept for mass-assignment proxying
    ];

    protected $casts = [];

    // Proxy accessors for backward compatibility (delegating to settings())
    public function getPrivacyAttribute(): string
    {
        return $this->pendingSettings['privacy'] ?? ($this->settings->privacy ?? 'public');
    }

    public function setPrivacyAttribute(string $value): void
    {
        $this->pendingSettings['privacy'] = $value;
    }

    public function getIsCollaborativeAttribute(): bool
    {
        return (bool) ($this->pendingSettings['is_collaborative'] ?? ($this->settings->is_collaborative ?? false));
    }

    public function setIsCollaborativeAttribute(bool $value): void
    {
        $this->pendingSettings['is_collaborative'] = $value;
    }

    public function getCoverImageAttribute(): ?string
    {
        return $this->pendingSettings['cover_image'] ?? ($this->settings->cover_image ?? null);
    }

    public function setCoverImageAttribute(?string $value): void
    {
        $this->pendingSettings['cover_image'] = $value;
    }

    public function getStatusAttribute(): ?string
    {
        return $this->pendingSettings['status'] ?? ($this->settings->status ?? null);
    }

    public function setStatusAttribute(?string $value): void
    {
        $this->pendingSettings['status'] = $value;
    }



    public function getIsPrivateAttribute(): bool
    {
        return $this->getPrivacyAttribute() === 'private';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->owner();
    }

    public function settings(): HasOne
    {
        return $this->hasOne(AlbumSettings::class);
    }


    public function photos(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function sharedLinks(): MorphMany
    {
        return $this->morphMany(SharedLink::class, 'shareable');
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    // Scopes
    public function scopePublic($query)
    {
        return $query->whereHas('settings', function ($q) {
            $q->where('privacy', 'public');
        });
    }

    public function scopeOwnedBy($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeCollaborative($query, User $user)
    {
        return $query->whereHas('settings', function ($q) {
            $q->where('is_collaborative', true);
        })->whereHas('collaborators', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }
}
