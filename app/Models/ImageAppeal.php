<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageAppeal extends Model
{
    protected $fillable = [
        'image_id',
        'user_id',
        'contact_name',
        'contact_email',
        'reason',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function image()
    {
        return $this->belongsTo(Image::class)->withoutGlobalScopes();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
