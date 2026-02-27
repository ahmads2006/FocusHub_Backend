<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasUuids, HasRoles, InteractsWithMedia;

    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_picture',
        'bio',
        'google2fa_secret',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function collaborativeAlbums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class)
                    ->withPivot('role')
                    ->withTimestamps();
    }
}
