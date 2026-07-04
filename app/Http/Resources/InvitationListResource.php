<?php

namespace App\Http\Resources;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invitation
 */
class InvitationListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'view_count' => $this->view_count,
            'public_url' => $this->publicUrl(),
            'template' => [
                'key' => $this->template?->key,
                'name' => $this->template?->name,
                'thumbnail' => $this->template?->thumbnail ? asset($this->template->thumbnail) : null,
            ],
            'counts' => [
                'rsvps' => $this->whenCounted('rsvps'),
                'guestbook' => $this->whenCounted('guestbook_messages'),
            ],
            'updated_at' => $this->updated_at,
        ];
    }
}
