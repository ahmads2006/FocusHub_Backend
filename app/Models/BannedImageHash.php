<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BannedImageHash extends Model
{
    use HasFactory;

    protected $fillable = ['hash', 'reason', 'details'];

    protected $casts = [
        'details' => 'array',
    ];
}
