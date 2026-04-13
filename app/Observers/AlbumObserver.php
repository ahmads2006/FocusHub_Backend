<?php

namespace App\Observers;

use App\Models\Album;
use App\Models\Conversation;

class AlbumObserver
{
    /**
     * When an album title is updated, sync the group conversation name
     * if the user hasn't manually renamed the group.
     */
    public function updated(Album $album): void
    {
        if ($album->isDirty('title')) {
            Conversation::where('album_id', $album->id)
                ->where('is_name_custom', false)
                ->update(['name' => '📁 ' . $album->title]);
        }
    }
}
