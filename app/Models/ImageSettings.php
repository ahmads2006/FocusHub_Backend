<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageSettings extends Model
{
    use HasUuids;

    protected $table = 'image_settings';

    protected $fillable = [
        'image_id',
        'allow_download',
        'watermark_on_download',
        'watermark_font_size',
        'watermark_opacity',
        'watermark_color',
        'watermark_type',
        'watermark_text',
        'copyright_enabled',
        'is_comparison',
        'views_count',
        'downloads_count',
    ];

    protected $casts = [
        'allow_download'        => 'boolean',
        'watermark_on_download' => 'boolean',
        'copyright_enabled'     => 'boolean',
        'is_comparison'         => 'boolean',
        'watermark_font_size'   => 'integer',
        'watermark_opacity'     => 'integer',
    ];

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }
}
