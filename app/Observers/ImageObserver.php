<?php

namespace App\Observers;

use App\Models\Image;
use App\Notifications\AlbumActivityNotification;
use Illuminate\Support\Facades\Auth;

class ImageObserver
{
    /**
     * Handle the Image "created" event.
     */
    public function created(Image $image): void
    {
        $this->notifyCollaborators($image, 'uploaded');
    }

    /**
     * Handle the Image "deleted" event.
     */
    public function deleted(Image $image): void
    {
        $this->notifyCollaborators($image, 'deleted');
    }

    /**
     * Notify all collaborators in the album except the actor.
     */
    protected function notifyCollaborators(Image $image, string $action): void
    {
        if (!$image->album_id) {
            return;
        }

        $album = $image->album;
        $actor = Auth::user();

        if (!$album || !$actor) {
            return;
        }

        // Get owner and collaborators
        $recipients = $album->collaborators->merge([$album->user])->unique('id');

        foreach ($recipients as $recipient) {
            if ($recipient->id !== $actor->id) {
                $recipient->notify(new AlbumActivityNotification($actor, $album, $action));
            }
        }
    }
}
