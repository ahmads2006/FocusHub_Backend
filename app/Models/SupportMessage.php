<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'is_system',
        'is_faq',
    ];

    protected $touches = ['conversation'];

    protected $casts = [
        'is_system' => 'boolean',
        'is_faq' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
