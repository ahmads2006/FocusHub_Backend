<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Album extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'is_private',
        'is_collaborative',
        'cover_image',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'is_collaborative' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
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
}
