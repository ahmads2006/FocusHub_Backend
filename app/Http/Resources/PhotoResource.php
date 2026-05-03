<?php

namespace App\Http\Resources;

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

        return [
            'id' => $this->id,
            'title' => $this->title,
            'display_title' => $this->display_title,
            'description' => $this->description,
            'ai_caption' => $this->ai_caption,
            'privacy' => $this->privacy,
            'status' => $this->status,

            // Signed/secure URLs used by frontend modal and download flows.
            'url' => $this->url,
            'url_high' => $this->url,
            'url_thumbnail' => $this->url_thumbnail,
            'url_tiny' => $this->url_tiny,
            'original_url' => $canDownload ? $this->resource->original_url : null,
            'srcset' => $this->srcset,

            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),

            'labels' => array_values($labels),
            'tags' => $tags,
            'comments' => $comments,
            'can_download' => $this->allow_download && $canDownload,
            'allow_download' => $this->allow_download,

            'settings' => $this->whenLoaded('settings'),
            'meta' => $this->whenLoaded('meta'),
            'user' => $this->whenLoaded('user'),
            'match_count' => $this->when(isset($this->match_count), $this->match_count),
            '_t' => time(),
        ];
    }
}
