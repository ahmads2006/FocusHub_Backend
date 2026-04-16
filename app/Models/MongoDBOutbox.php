<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MongoDBOutbox extends Model
{
    use HasUuids;

    protected $table = 'mongodb_outbox';

    protected $fillable = [
        'id',
        'collection',
        'operation',
        'payload',
        'status',
        'attempts',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
