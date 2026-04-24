<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
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
    use HasApiTokens, HasFactory, Notifiable, HasUuids, HasRoles, InteractsWithMedia, LogsActivity, HasTags;
    
    /**
     * Temporary storage for attributes that belong to related models.
     * This avoids SQL errors during the creation of the User model.
     */
    protected array $relationData = [];

    protected static function booted(): void
    {
        static::created(function (User $user) {
            $user->userStatus()->create([]);
            
            // Extract deferred data for the profile
            $profileData = $user->getRelationData('profile');
            $displayName = $profileData['name'] ?? 'User';
            $handle = \App\Helpers\RestrictedNameHelper::generateUniqueHandle($displayName);

            $user->profile()->create(array_merge([
                'name' => $displayName,
                'username' => $handle,
                'username_last_changed_at' => now(),
            ], $profileData));

            // Extract deferred data for settings
            $settingsData = $user->getRelationData('settings');
            $user->settings()->create(array_merge([
                'dynamic_watermark' => false,
                'watermark_text_color' => '#FFFFFF',
                'watermark_neon_color' => '#00FFFF',
                'watermark_opacity' => 0.5,
                'watermark_mode' => 'text',
                'auto_orient_default' => true,
                'stay_logged_in' => false,
                'is_public_profile' => true,
            ], $settingsData));

            // Extract deferred data for verification
            $verificationData = $user->getRelationData('verification');
            $user->verification()->create(array_merge([
                'verification_code' => null,
                'is_verified' => false,
            ], $verificationData));

            // Extract deferred data for oauth
            $oauthData = $user->getRelationData('oauth');
            if (!empty($oauthData)) {
                $user->oauth()->create($oauthData);
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
        // Still in fillable to allow creating users with these fields (handled by accessors/mutators or boots)
        'name',
        'username',
        'bio',
        'profile_picture',
        'dynamic_watermark',
        'watermark_text_color',
        'watermark_neon_color',
        'watermark_opacity',
        'auto_orient_default',
        'stay_logged_in',
        'google_id',
        'adobe_id',
        'instagram_id',
        'provider_name',
        'provider_id',
        'provider_avatar',
        'avatar',
        'provider_token',
        'is_public_profile',
        'watermark_text',
        'watermark_logo',
        'watermark_mode',
        'notification_preferences',
    ];  
    
    protected $with = ['userStatus', 'profile' ];

    
    protected $appends = ['can_edit'];

    protected $hidden = [
        'password',
        'remember_token',
        'email', // Hide email by default for privacy
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
            'storage_limit_bytes' => 'integer',
            'storage_used_bytes' => 'integer',
            'notification_preferences' => 'array',
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
        // 1. Check if it's in temporary relationData (unsaved changes)
        if (isset($this->relationData[$relation][$column])) {
            return $this->relationData[$relation][$column];
        }

        // 2. Check if the relation is already loaded
        if ($this->relationLoaded($relation) && $this->{$relation}) {
            return $this->{$relation}->{$column} ?? $default;
        }

        // 3. Fallback to main attributes (for backward compatibility during migration)
        if (isset($this->attributes[$column])) {
            return $this->attributes[$column];
        }

        // 4. Lazy-load as a last resort if we are not in a serialization context
        // Note: During serialization (toArray/toJson), we should avoid lazy loading
        if ($this->exists && !isset($this->attributes[$column])) {
            $rel = $this->{$relation}; // This triggers lazy loading
            return $rel ? ($rel->{$column} ?? $default) : $default;
        }

        return $default;
    }

    protected function setRelatedAttribute($relation, $column, $value)
    {
        if ($this->exists) {
            // Ensure relation is loaded
            $rel = $this->{$relation};
            if ($rel) {
                $rel->{$column} = $value;
                // If the relation is dirty, we don't need to do more here
                // as save() is overridden to save relations.
                return;
            }
        }
        
        // During creation or if relation is missing, store in relationData
        $this->relationData[$relation][$column] = $value;
    }

    /**
     * Helper to extract temporary relation data.
     */
    public function getRelationData($relation): array
    {
        return $this->relationData[$relation] ?? [];
    }

    // Accessors & Mutators for Backward Compatibility
    public function getNameAttribute() { return $this->getRelatedAttribute('profile', 'name'); }
    public function setNameAttribute($value) { $this->setRelatedAttribute('profile', 'name', $value); }

    public function getUsernameAttribute() { return $this->getRelatedAttribute('profile', 'username'); }
    public function setUsernameAttribute($value) { $this->setRelatedAttribute('profile', 'username', $value); }

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

    public function getWatermarkTextAttribute() { return $this->getRelatedAttribute('settings', 'watermark_text'); }
    public function setWatermarkTextAttribute($value) { $this->setRelatedAttribute('settings', 'watermark_text', $value); }

    public function getWatermarkLogoAttribute() { return $this->getRelatedAttribute('settings', 'watermark_logo'); }
    public function setWatermarkLogoAttribute($value) { $this->setRelatedAttribute('settings', 'watermark_logo', $value); }

    public function getWatermarkModeAttribute() { return $this->getRelatedAttribute('settings', 'watermark_mode', 'text'); }
    public function setWatermarkModeAttribute($value) { $this->setRelatedAttribute('settings', 'watermark_mode', $value); }

    public function getWatermarkNeonColorAttribute() { return $this->getRelatedAttribute('settings', 'watermark_neon_color'); }
    public function setWatermarkNeonColorAttribute($value) { $this->setRelatedAttribute('settings', 'watermark_neon_color', $value); }

    public function getWatermarkOpacityAttribute() { return (float) $this->getRelatedAttribute('settings', 'watermark_opacity', 0.5); }
    public function setWatermarkOpacityAttribute($value) { $this->setRelatedAttribute('settings', 'watermark_opacity', $value); }

    public function getAutoOrientDefaultAttribute() { return (bool) $this->getRelatedAttribute('settings', 'auto_orient_default', true); }
    public function setAutoOrientDefaultAttribute($value) { $this->setRelatedAttribute('settings', 'auto_orient_default', $value); }

    public function getStayLoggedInAttribute() { return (bool) $this->getRelatedAttribute('settings', 'stay_logged_in', false); }
    public function setStayLoggedInAttribute($value) { $this->setRelatedAttribute('settings', 'stay_logged_in', $value); }

    public function getIsPublicProfileAttribute() { return (bool) $this->getRelatedAttribute('settings', 'is_public_profile', true); }
    public function setIsPublicProfileAttribute($value) { $this->setRelatedAttribute('settings', 'is_public_profile', $value); }

    public function getGoogleIdAttribute() { return $this->getRelatedAttribute('oauth', 'google_id'); }
    public function setGoogleIdAttribute($value) { $this->setRelatedAttribute('oauth', 'google_id', $value); }

    public function getAdobeIdAttribute() { return $this->getRelatedAttribute('oauth', 'adobe_id'); }
    public function setAdobeIdAttribute($value) { $this->setRelatedAttribute('oauth', 'adobe_id', $value); }

    public function getProviderTokenAttribute() { return $this->getRelatedAttribute('oauth', 'provider_token'); }
    public function setProviderTokenAttribute($value) { $this->setRelatedAttribute('oauth', 'provider_token', $value); }

    /**
     * Get the roles assigned to the user.
     */
    public function getRoleNamesAttribute()
    {
        return $this->getRoleNames();
    }

    /**
     * Get all permissions assigned to the user (including those via roles).
     */
    public function getPermissionNamesAttribute()
    {
        return $this->getAllPermissions()->pluck('name');
    }

    /**
     * Check if the authenticated user can edit this user profile.
     */
    public function getCanEditAttribute(): bool
    {
        return auth()->id() === $this->id;
    }

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


    /**
     * علامة التوثيق الزرقاء (Blue Badge).
     * تُمنح فقط لمن لديه 100 صورة معتمدة (خضراء) أو للأدمن بشكل استثنائي.
     * هذا منفصل تماماً عن is_verified (تحقق البريد الإلكتروني).
     */
    public function getIsBadgeVerifiedAttribute(): bool
    {
        // الأدمن يحصل على العلامة دائماً
        if ($this->isAnyAdmin() || in_array($this->role, ['support'])) {
            return true;
        }

        // عدد الصور المعتمدة (المنطقة الخضراء) >= 100
        return $this->images()
            ->whereHas('moderation', function ($q) {
                $q->where('status', Image::STATUS_APPROVED);
            })
            ->count() >= 100;
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
        $code = (string) random_int(100000, 999999);

        // Ensure UserVerification record exists and update it
        $verification = $this->verification()->firstOrCreate([]);
        $verification->update([
            'verification_code' => $code,
            'is_verified' => false
        ]);
        
        // Update the timestamp to reset expiration
        $verification->touch();

        Mail::to($this->email)->queue(new VerificationCodeMail($code, $this->name));
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

    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function bookmarkedImages(): BelongsToMany
    {
        return $this->belongsToMany(Image::class, 'bookmarks', 'user_id', 'image_id')->withTimestamps();
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function acceptedConnections()
    {
        return User::whereIn('id', function($query) {
            $query->select('connected_user_id')
                ->from('connections')
                ->where('user_id', $this->id)
                ->where('status', 'accepted')
                ->union(
                    $query->newQuery()->select('user_id')
                    ->from('connections')
                    ->where('connected_user_id', $this->id)
                    ->where('status', 'accepted')
                );
        });
    }

    /**

     * الحصول على رابط الصورة الشخصية أو صورة افتراضية.
     * يستخدم النسخة المحسنة (avatar.webp) إذا كانت متوفرة.
     */
    public function getAvatarAttribute(): string
    {
        // 1. Check custom uploaded profile picture (Manual change)
        // We prefer this over the social provider avatar once the user changes it.
        $profilePicture = $this->profile ? $this->profile->profile_picture : ($this->attributes['profile_picture'] ?? null);
        
        if ($profilePicture) {
            if (filter_var($profilePicture, FILTER_VALIDATE_URL)) {
                return $profilePicture;
            }
            return asset('storage/' . $profilePicture);
        }

        // 2. Check provider_avatar (Social Login)
        if ($this->provider_avatar && filter_var($this->provider_avatar, FILTER_VALIDATE_URL)) {
            return $this->provider_avatar;
        }

        // 3. Fallback to UI Avatars
        $name = $this->name ?? 'User';
        return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&color=7F9CF5&background=EBF4FF&size=150';
    }


    /**
     * حساب إجمالي المساحة المستخدمة (5GB Limit System)
     */
    public function getStorageUsedPercentageAttribute(): float
    {
        if (!$this->storage_limit_bytes || $this->storage_limit_bytes === 0) {
            return 0;
        }

        return round(($this->storage_used_bytes / $this->storage_limit_bytes) * 100, 2);
    }

    public function getStorageRemainingBytesAttribute(): int
    {
        if (!$this->storage_limit_bytes) {
            return 999999999999; // Infinite for Super Admin
        }

        return max(0, $this->storage_limit_bytes - $this->storage_used_bytes);
    }

    /**
     * التأكد من وجود مساحة كافية قبل الرفع.
     */
    public function hasEnoughStorage(int $bytesToAdd): bool
    {
        // Super Admins have infinite space
        if ($this->hasRole('super-admin') || $this->hasRole('super_admin') || !$this->storage_limit_bytes) {
            return true;
        }

        return ($this->storage_used_bytes + $bytesToAdd) <= $this->storage_limit_bytes;
    }

    /**
     * تحديث المساحة المستخدمة يدوياً (للطوارئ أو إعادة الحساب)
     */
    public function recalculateStorageUsed(): int
    {
        $total = (int) $this->images()->sum('size');
        $this->update(['storage_used_bytes' => $total]);
        return $total;
    }

    /**
     * Check if the user has any administrative role.
     */
    public function isAnyAdmin(): bool
    {
        $adminRoles = ['admin', 'super_admin', 'super-admin'];
        
        return in_array($this->role, $adminRoles) || 
               $this->hasAnyRole($adminRoles) || 
               $this->can('access-admin-panel');
    }
}
