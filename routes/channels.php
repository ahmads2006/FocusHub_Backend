<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// ── Group Chat Presence Channel ──────────────────────────
Broadcast::channel('group-chat.{conversationId}', function ($user, $conversationId) {
    $isParticipant = \App\Models\Conversation::where('id', $conversationId)
        ->whereHas('participants', fn($q) => $q->where('users.id', $user->id))
        ->exists();

    if ($isParticipant) {
        return [
            'id'     => $user->id,
            'name'   => $user->name,
            'avatar' => $user->avatar,
        ];
    }

    return false;
});
