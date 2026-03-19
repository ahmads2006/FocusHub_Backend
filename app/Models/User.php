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
            $user->profile()->create([
                'name' => $user->getAttribute('name'),
                'bio' => $user->getAttribute('bio'),
                'profile_picture' => $user->getAttribute('profile_picture'),
                'avatar' => $user->getAttribute('avatar'),
            ]);
            $user->settings()->create([
                'dynamic_watermark' => $user->getAttribute('dynamic_watermark') ?? false,
                'watermark_text_color' => $user->getAttribute('watermark_text_color') ?? '#FFFFFF',
                'watermark_neon_color' => $user->getAttribute('watermark_neon_color') ?? '#00FFFF',
                'watermark_opacity' => $user->getAttribute('watermark_opacity') ?? 0.5,
                'auto_orient_default' => $user->getAttribute('auto_orient_default') ?? true,
                'stay_logged_in' => $user->getAttribute('stay_logged_in') ?? false,
            ]);
            $user->verification()->create([
                'verification_code' => $user->getAttribute('verification_code'),
                'is_verified' => $user->getAttribute('is_verified') ?? false,
            ]);
            if ($user->getAttribute('google_id')) {
                $user->oauth()->create([
                    'google_id' => $user->getAttribute('google_id'),
                    'provider_token' => $user->getAttribute('provider_token'),
                ]);
            }
        });
    }

    public function hasVerifiedEmail()
    {
        return $this->is_verified;
    }

    public function markEmailAsVerified()
    {
        return $this->verification->update(['is_verified' => true]);
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
        'email',
        'password',
        'role',
        // Still in fillable to allow creating users with these fields (handled by accessors/mutators or boots)
        'name',
        'bio',
        'profile_picture',
        'verification_code',
        'dynamic_watermark',
        'watermark_text_color',
        'watermark_neon_color',
        'watermark_opacity',
        'auto_orient_default',
        'is_verified',
        'stay_logged_in',
        'google_id',
        'avatar',
        'provider_token',
    ];

    protected $with = ['userStatus', 'profile', 'settings', 'verification', 'oauth'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
        ];
    }

    // Relationships
    public function userStatus(): HasOne { return $this->hasOne(UserStatus::class); }
    public function profile(): HasOne { return $this->hasOne(UserProfile::class); }
    public function settings(): HasOne { return $this->hasOne(UserSetting::class); }
    public function verification(): HasOne { return $this->hasOne(UserVerification::class); }
    public function oauth(): HasOne { return $this->hasOne(UserOAuth::class); }
    public function likes(): HasMany { return $this->hasMany(Like::class); }
    public function preference(): HasOne { return $this->hasOne(UserPreference::class); }

    /**
     * Helper to get or set attributes in related tables.
     */
    protected function getRelatedAttribute($relation, $column, $default = null)
    {
        if ($this->relationLoaded($relation)) {
            return $this->{$relation}->{$column} ?? $default;
        }
        return $this->attributes[$column] ?? ($this->{$relation}->{$column} ?? $default);
    }

    protected function setRelatedAttribute($relation, $column, $value)
    {
        if ($this->exists) {
            // This will lazy-load the relation if not loaded
            $rel = $this->{$relation};
            if ($rel) {
                $rel->{$column} = $value;
                return;
            }
        }
        $this->attributes[$column] = $value;
    }

    // Accessors & Mutators for Backward Compatibility
    public function getNameAttribute() { return $this->getRelatedAttribute('profile', 'name'); }
    public function setNameAttribute($value) { $this->setRelatedAttribute('profile', 'name', $value); }

    public function getBioAttribute() { return $this->getRelatedAttribute('profile', 'bio'); }
    public function setBioAttribute($value) { $this->setRelatedAttribute('profile', 'bio', $value); }

    public function getProfilePictureAttribute() { return $this->getRelatedAttribute('profile', 'profile_picture'); }
    public function setProfilePictureAttribute($value) { $this->setRelatedAttribute('profile', 'profile_picture', $value); }

    public function getVerificationCodeAttribute() { return $this->getRelatedAttribute('verification', 'verification_code'); }
    public function setVerificationCodeAttribute($value) { $this->setRelatedAttribute('verification', 'verification_code', $value); }

    public function getIsVerifiedAttribute() { return (bool) $this->getRelatedAttribute('verification', 'is_verified', false); }
    public function setIsVerifiedAttribute($value) { $this->setRelatedAttribute('verification', 'is_verified', $value); }

    public function getDynamicWatermarkAttribute() { return (bool) $this->getRelatedAttribute('settings', 'dynamic_watermark', false); }
    public function setDynamicWatermarkAttribute($value) { $this->setRelatedAttribute('settings', 'dynamic_watermark', $value); }

    public function getWatermarkTextColorAttribute() { return $this->getRelatedAttribute('settings', 'watermark_text_color'); }
    public function setWatermarkTextColorAttribute($value) { $this->setRelatedAttribute('settings', 'watermark_text_color', $value); }

    public function getWatermarkNeonColorAttribute() { return $this->getRelatedAttribute('settings', 'watermark_neon_color'); }
    public function setWatermarkNeonColorAttribute($value) { $this->setRelatedAttribute('settings', 'watermark_neon_color', $value); }

    public function getWatermarkOpacityAttribute() { return (float) $this->getRelatedAttribute('settings', 'watermark_opacity', 0.5); }
    public function setWatermarkOpacityAttribute($value) { $this->setRelatedAttribute('settings', 'watermark_opacity', $value); }

    public function getAutoOrientDefaultAttribute() { return (bool) $this->getRelatedAttribute('settings', 'auto_orient_default', true); }
    public function setAutoOrientDefaultAttribute($value) { $this->setRelatedAttribute('settings', 'auto_orient_default', $value); }

    public function getStayLoggedInAttribute() { return (bool) $this->getRelatedAttribute('settings', 'stay_logged_in', false); }
    public function setStayLoggedInAttribute($value) { $this->setRelatedAttribute('settings', 'stay_logged_in', $value); }

    public function getGoogleIdAttribute() { return $this->getRelatedAttribute('oauth', 'google_id'); }
    public function setGoogleIdAttribute($value) { $this->setRelatedAttribute('oauth', 'google_id', $value); }

    public function getProviderTokenAttribute() { return $this->getRelatedAttribute('oauth', 'provider_token'); }
    public function setProviderTokenAttribute($value) { $this->setRelatedAttribute('oauth', 'provider_token', $value); }

    /**
     * Override save to also save related models if they are loaded.
     */
    public function save(array $options = [])
    {
        $saved = parent::save($options);
        if ($saved) {
            // Save relations if they are loaded (they might have been modified via mutators)
            if ($this->relationLoaded('profile') && $this->profile) $this->profile->save();
            if ($this->relationLoaded('settings') && $this->settings) $this->settings->save();
            if ($this->relationLoaded('verification') && $this->verification) $this->verification->save();
            if ($this->relationLoaded('oauth') && $this->oauth) $this->oauth->save();
        }
        return $saved;
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
     * يستخدم النسخة المحسنة (avatar.webp) إذا كانت متوفرة.
     */
    public function getAvatarAttribute(): string
    {
        $profilePicture = $this->profile ? $this->profile->profile_picture : ($this->attributes['profile_picture'] ?? null);
        
        if ($profilePicture) {
            // If it's a full URL (like UI Avatars), return it
            if (filter_var($profilePicture, FILTER_VALIDATE_URL)) {
                return $profilePicture;
            }

            // Return the stored path (which is already optimized to 150x150 WebP)
            return asset('storage/' . $profilePicture);
        }

        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF&size=150';
    }

}
