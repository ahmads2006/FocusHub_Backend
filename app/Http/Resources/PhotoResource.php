<?php

namespace App\Http\Resources;

use App\Helpers\MediaHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhotoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canDownload = \Illuminate\Support\Facades\Gate::allows('download', $this->resource);
        $labels = is_array($this->labels) ? $this->labels : [];

        $tags = $this->relationLoaded('tags')
            ? $this->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ];
            })->values()->all()
            : [];

        $comments = $this->relationLoaded('comments')
            ? $this->comments->values()->all()
            : [];

        $frame = MediaHelper::resolveGalleryFrame(
            $this->relationLoaded('meta') ? $this->meta : null
        );

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'display_title' => $this->display_title,
            'description' => $this->description,
            'ai_caption' => $this->ai_caption,
            'privacy' => $this->privacy,
            'status' => $this->status,
            'moderation_status' => $this->moderation?->status ?? 'approved',
            'is_sensitive' => (bool) (
                $this->moderation?->is_sensitive
                || in_array($this->moderation?->status, ['pending_review', 'under_review'], true)
            ),
            'sensitivity_reason' => $this->moderation?->sensitivity_reason,

            // Signed/secure URLs used by frontend modal and download flows.
            'url' => $this->url,
            'url_high' => $this->url,
            'url_thumbnail' => $this->url_thumbnail,
            'url_tiny' => $this->url_tiny,
            'original_url' => $canDownload ? $this->resource->original_url : null,
            'srcset' => $this->srcset,

            // Gallery layout: reserve real frame space before images load
            'width' => $frame['width'],
            'height' => $frame['height'],
            'orientation' => $frame['orientation'],
            'aspect_ratio' => $frame['aspect_ratio'],

            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),

            'labels' => array_values($labels),
            'tags' => $tags,
            'comments' => $comments,
            'can_download' => $canDownload,
            'allow_download' => $this->allow_download,

            'settings' => $this->whenLoaded('settings'),
            'meta' => $this->whenLoaded('meta'),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'username' => $this->user->username,
                    'avatar' => $this->user->avatar,
                ];
            }),
            'match_count' => $this->when(isset($this->match_count), $this->match_count),
            
            'is_liked' => $this->when(isset($this->is_liked), $this->is_liked, function() {
                return \Illuminate\Support\Facades\Auth::check() ? \App\Models\Like::where('user_id', \Illuminate\Support\Facades\Auth::id())->where('image_id', $this->id)->exists() : false;
            }),
            'is_saved' => $this->when(isset($this->is_saved), $this->is_saved, function() {
                return \Illuminate\Support\Facades\Auth::check() ? \App\Models\Bookmark::where('user_id', \Illuminate\Support\Facades\Auth::id())->where('image_id', $this->id)->exists() : false;
            }),
            'likes_count' => (int) ($this->likes_count ?? $this->resource->likes_count ?? 0),
            'saves_count' => (int) ($this->bookmarks_count ?? $this->resource->bookmarks_count ?? 0),
            'likes' => (int) ($this->likes_count ?? $this->resource->likes_count ?? 0),
            'bookmarks' => (int) ($this->bookmarks_count ?? $this->resource->bookmarks_count ?? 0),
            
            '_t' => time(),
        ];
    }
}
