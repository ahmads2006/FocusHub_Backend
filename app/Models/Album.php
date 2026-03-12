<?php

namespace App\Models;

use App\Models\Scopes\ShadowPrivacyScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Album extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, LogsActivity;

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
        'title',
        'description',
        'privacy',
        'status',
        'is_collaborative',
        'cover_image',
    ];

    protected $casts = [
        'privacy' => 'string',
        'is_collaborative' => 'boolean',
    ];

    public function getIsPrivateAttribute(): bool
    {
        return $this->privacy === 'private';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->owner();
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
        return $query->where('privacy', 'public');
    }

    public function scopeOwnedBy($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeCollaborative($query, User $user)
    {
        return $query->where('is_collaborative', true)
                     ->whereHas('collaborators', function ($q) use ($user) {
                         $q->where('user_id', $user->id);
                     });
    }
}
