<?php

namespace App\Models\Mongo;

use MongoDB\Laravel\Eloquent\Model as MongoDBModel;

class ChatMessage extends MongoDBModel
{
    // Uses the mongodb connection defined in config/database.php
    protected $connection = 'mongodb';
    
    // Uses the chat_messages collection
    protected $collection = 'chat_messages';

    protected $fillable = [
        'id', // Using custom UUID
        'sender_id',
        'receiver_id',
        'conversation_id',
        'album_id',
        'body',
        'image_id',
        'image_url',
        'thumb_url',
        'is_read',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
