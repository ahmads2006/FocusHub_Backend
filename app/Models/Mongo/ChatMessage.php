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

    /**
     * Relationship to the MySQL Image model.
     */
    public function image()
    {
        return $this->belongsTo(\App\Models\Image::class, 'image_id');
    }

    /**
     * Relationship to the MySQL User model (Sender).
     */
    public function sender()
    {
        return $this->belongsTo(\App\Models\User::class, 'sender_id');
    }

    /**
     * Relationship to the MySQL User model (Receiver).
     */
    public function receiver()
    {
        return $this->belongsTo(\App\Models\User::class, 'receiver_id');
    }
}
