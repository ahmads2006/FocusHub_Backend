<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Tags\HasTags;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Mail;

class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasUuids, HasRoles, InteractsWithMedia, LogsActivity, HasTags;

    protected static function booted(): void
    {
        static::created(function (User $user) {
            $user->userStatus()->create([]);
        });
    }

    public function hasVerifiedEmail()
    {
        return $this->is_verified;
    }

    public function markEmailAsVerified()
    {
        return $this->forceFill([
            'is_verified' => true,
        ])->save();
    }

    /**
     * Override the default method to prevent Route [verification.verify] not defined error.
     * We send our custom verification code email separately.
     */
    public function sendEmailVerificationNotification()
    {
        // Do nothing here.
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_picture',
        'bio',
        'verification_code',
        'is_verified',
        'role',
        'stay_logged_in',
    ];

    protected $with = ['userStatus'];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
        
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'role' => 'string',
            'stay_logged_in' => 'boolean',
        ];
    }

    public function userStatus(): HasOne
    {
        return $this->hasOne(UserStatus::class);
    }

    public function getIsBannedAttribute(): bool
    {
        return $this->userStatus?->is_banned ?? false;
    }

    public function getIsShadowHiddenAttribute(): bool
    {
        return $this->userStatus?->is_shadow_hidden ?? false;
    }

    public function getBannedAtAttribute(): ?\Carbon\Carbon
    {
        return $this->userStatus?->banned_at;
    }

    /**
     * توليد رمز تحقق عشوائي وإرساله عبر البريد الإلكتروني.
     */
    public function sendVerificationEmail(): void
    {
        $code = (string) rand(100000, 999999);

        $this->update(['verification_code' => $code]);

        Mail::to($this->email)->send(new VerificationCodeMail($code, $this->name));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function ownedAlbums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    public function collaborativeAlbums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class)
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    /**
     * الحصول على رابط الصورة الشخصية أو صورة افتراضية.
     */
    public function getAvatarAttribute(): string
    {
        if ($this->profile_picture) {
            return asset('storage/' . $this->profile_picture);
        }

        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
    }
}
