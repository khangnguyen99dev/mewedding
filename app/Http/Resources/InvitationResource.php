<?php

namespace App\Http\Resources;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invitation
 */
class InvitationResource extends JsonResource
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
            'locale' => $this->locale,
            'template' => [
                'key' => $this->template->key,
                'name' => $this->template->name,
            ],
            'settings' => (object) ($this->settings ?? []),
            'theme' => (object) ($this->theme ?? []),
            'seo' => (object) ($this->seo ?? []),
            'public_url' => $this->publicUrl(),
            'media' => [
                'images' => $this->getMedia(Invitation::COLLECTION_LIBRARY)->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->file_name,
                    'thumb' => $m->hasGeneratedConversion('thumb') ? $m->getUrl('thumb') : $m->getUrl(),
                    'web' => $m->hasGeneratedConversion('web') ? $m->getUrl('web') : $m->getUrl(),
                    'full' => $m->getUrl(),
                    'alt' => (string) $m->getCustomProperty('alt', ''),
                ])->values(),
                'audio' => $this->getMedia(Invitation::COLLECTION_AUDIO)->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->file_name,
                    'url' => $m->getUrl(),
                ])->values(),
            ],
            'counts' => [
                'rsvps' => $this->rsvps()->count(),
                'guestbook' => $this->guestbookMessages()->count(),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
