<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'tag_weights',
        'creator_weights',
    ];

    protected $casts = [
        'tag_weights' => 'array',
        'creator_weights' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
