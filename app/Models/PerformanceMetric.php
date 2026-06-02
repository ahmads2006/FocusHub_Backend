<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'metric_type',
        'path',
        'ts',
        'lcp',
        'inp',
        'cls',
        'pass_lcp',
        'pass_inp',
        'pass_cls',
        'prefetch_attempts',
        'prefetch_success',
        'prefetch_failed',
        'prefetch_skipped',
        'user_agent',
        'session_id',
        'meta',
    ];

    protected $casts = [
        'ts' => 'datetime',
        'lcp' => 'float',
        'inp' => 'float',
        'cls' => 'float',
        'pass_lcp' => 'boolean',
        'pass_inp' => 'boolean',
        'pass_cls' => 'boolean',
        'prefetch_attempts' => 'integer',
        'prefetch_success' => 'integer',
        'prefetch_failed' => 'integer',
        'prefetch_skipped' => 'integer',
        'meta' => 'array',
    ];
}
