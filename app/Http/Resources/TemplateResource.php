<?php

namespace App\Http\Resources;

use App\Models\Template;
use App\Services\TemplateRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Template
 */
class TemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Read the manifest from the filesystem registry (which preserves section
        // key order) rather than the DB JSON column (MySQL does not keep object
        // key order). Falls back to the cached DB manifest if the folder is gone.
        $manifest = app(TemplateRegistry::class)->find($this->key) ?? $this->manifest ?? [];

        return [
            'key' => $this->key,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail ? asset($this->thumbnail) : null,
            'status' => $this->status,
            // Full schema: drives the dynamic form generator + theme editor.
            'sections' => $manifest['sections'] ?? [],
            'theme' => $manifest['theme'] ?? [],
            'theme_schema' => $manifest['theme_schema'] ?? [],
        ];
    }
}
