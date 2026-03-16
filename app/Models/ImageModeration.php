<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageModeration extends Model
{
    use HasUuids;

    protected $table = 'image_moderation';

    const STATUS_PENDING        = 'pending';
    const STATUS_APPROVED       = 'approved';
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_REJECTED       = 'rejected';
    const STATUS_UNDER_REVIEW   = 'under_review';

    protected $fillable = [
        'image_id',
        'status',
        'is_visible',
        'is_sensitive',
        'sensitivity_reason',
        'ai_metadata',
    ];

    protected $casts = [
        'is_visible'   => 'boolean',
        'is_sensitive' => 'boolean',
        'ai_metadata'  => 'json',
    ];

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }

    public function isApproved(): bool   { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool   { return $this->status === self::STATUS_REJECTED; }
    public function isPendingReview(): bool { return $this->status === self::STATUS_PENDING_REVIEW; }
}
