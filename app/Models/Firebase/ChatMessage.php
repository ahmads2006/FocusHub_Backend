<?php

namespace App\Models\Firebase;

use App\Services\Firebase\FirestoreService;

class ChatMessage
{
    public array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;

        if (isset($this->attributes['_id']) && !isset($this->attributes['id'])) {
            $this->attributes['id'] = $this->attributes['_id'];
        } elseif (isset($this->attributes['id']) && !isset($this->attributes['_id'])) {
            $this->attributes['_id'] = $this->attributes['id'];
        }
    }

    public function __get(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public static function find(string $id): ?self
    {
        $firestore = app(FirestoreService::class);
        $data = $firestore->getDocument('chat_messages', $id);
        return $data ? new self($data) : null;
    }

    public function update(array $attributes): bool
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        $firestore = app(FirestoreService::class);
        $firestore->setDocument('chat_messages', (string) $this->id, $this->attributes);
        return true;
    }

    public function delete(): bool
    {
        if (empty($this->id)) {
            return false;
        }
        $firestore = app(FirestoreService::class);
        return $firestore->deleteDocument('chat_messages', (string) $this->id);
    }

    /**
     * Relationship to sender User (MySQL)
     */
    public function sender()
    {
        if (empty($this->sender_id)) {
            return null;
        }
        return \App\Models\User::find($this->sender_id);
    }

    /**
     * Relationship to receiver User (MySQL)
     */
    public function receiver()
    {
        if (empty($this->receiver_id)) {
            return null;
        }
        return \App\Models\User::find($this->receiver_id);
    }

    /**
     * Relationship to Image (MySQL)
     */
    public function image()
    {
        if (empty($this->image_id)) {
            return null;
        }
        return \App\Models\Image::find($this->image_id);
    }
}
