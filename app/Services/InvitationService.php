<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class InvitationService
{
    public function __construct(private readonly TemplateRegistry $registry) {}

    /**
     * Create a new invitation from a template, pre-filled with the template's
     * demo content (preview.json) — including real media uploaded from the
     * template's bundled demo assets.
     *
     * @param array<string, mixed> $attributes
     */
    public function createFromTemplate(User $user, string $templateKey, array $attributes = []): Invitation
    {
        $template = Template::where('key', $templateKey)->where('status', 'active')->first();

        if (! $template) {
            throw new RuntimeException("Template [{$templateKey}] not found or inactive.");
        }

        $title = $attributes['title'] ?? trim(($user->name ?? 'Thiệp cưới'));

        $invitation = new Invitation([
            'title' => $title,
            'status' => Invitation::STATUS_DRAFT,
            'locale' => $attributes['locale'] ?? 'vi',
        ]);
        $invitation->user()->associate($user);
        $invitation->template()->associate($template);
        $invitation->slug = $this->uniqueSlug($attributes['slug'] ?? $title);
        $invitation->settings = [];
        $invitation->save();

        $this->importPreviewContent($invitation, $templateKey);

        return $invitation->refresh();
    }

    /**
     * Fill an invitation's settings/theme from a template's preview.json,
     * uploading any @image:/@audio: placeholder assets into the media library
     * and replacing the placeholders with the resulting media ids.
     */
    public function importPreviewContent(Invitation $invitation, string $templateKey): void
    {
        $preview = $this->registry->preview($templateKey);

        if ($preview === []) {
            return;
        }

        $uploaded = []; // filename => media id (dedupe repeated references)

        $settings = $this->walk(
            $preview['settings'] ?? [],
            $invitation,
            $templateKey,
            $uploaded,
        );

        $manifest = $this->registry->find($templateKey) ?? [];

        $invitation->settings = $settings;
        $invitation->theme = $preview['theme'] ?? $manifest['theme'] ?? [];
        $invitation->save();
    }

    /**
     * Recursively replace @image:/@audio: placeholders with uploaded media ids.
     *
     * @param mixed $value
     * @param array<string, int> $uploaded
     * @return mixed
     */
    protected function walk($value, Invitation $invitation, string $templateKey, array &$uploaded)
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->walk($v, $invitation, $templateKey, $uploaded), $value);
        }

        if (is_string($value) && str_starts_with($value, '@image:')) {
            return $this->uploadAsset($invitation, $templateKey, 'images', substr($value, 7), Invitation::COLLECTION_LIBRARY, $uploaded);
        }

        if (is_string($value) && str_starts_with($value, '@audio:')) {
            return $this->uploadAsset($invitation, $templateKey, 'media', substr($value, 7), Invitation::COLLECTION_AUDIO, $uploaded);
        }

        return $value;
    }

    /**
     * @param array<string, int> $uploaded
     */
    protected function uploadAsset(
        Invitation $invitation,
        string $templateKey,
        string $subdir,
        string $filename,
        string $collection,
        array &$uploaded,
    ): ?int {
        $cacheKey = "{$collection}:{$filename}";
        if (isset($uploaded[$cacheKey])) {
            return $uploaded[$cacheKey];
        }

        $path = $this->registry->templatePath($templateKey)."/assets/{$subdir}/{$filename}";

        if (! File::exists($path)) {
            return null;
        }

        $media = $invitation
            ->addMedia($path)
            ->preservingOriginal()
            ->toMediaCollection($collection);

        return $uploaded[$cacheKey] = $media->id;
    }

    /**
     * Apply core + content updates to an invitation.
     *
     * @param array<string, mixed> $data
     */
    public function update(Invitation $invitation, array $data): Invitation
    {
        if (array_key_exists('slug', $data) && $data['slug'] && $data['slug'] !== $invitation->slug) {
            $invitation->slug = $this->uniqueSlug($data['slug'], $invitation->id);
        }

        foreach (['title', 'locale', 'settings', 'theme', 'seo'] as $field) {
            if (array_key_exists($field, $data)) {
                $invitation->{$field} = $data[$field];
            }
        }

        $invitation->save();

        return $invitation;
    }

    public function publish(Invitation $invitation): Invitation
    {
        $invitation->update([
            'status' => Invitation::STATUS_PUBLISHED,
            'published_at' => $invitation->published_at ?? now(),
        ]);

        return $invitation;
    }

    public function unpublish(Invitation $invitation): Invitation
    {
        $invitation->update(['status' => Invitation::STATUS_DRAFT]);

        return $invitation;
    }

    /**
     * Duplicate an invitation, copying its media and remapping the media ids
     * stored in the settings to the new copies (schema-aware, so non-media
     * integers like guest counts are never touched).
     */
    public function duplicate(Invitation $invitation): Invitation
    {
        $copy = $invitation->replicate(['published_at', 'view_count']);
        $copy->status = Invitation::STATUS_DRAFT;
        $copy->published_at = null;
        $copy->view_count = 0;
        $copy->title = $invitation->title.' (Bản sao)';
        $copy->slug = $this->uniqueSlug($invitation->slug);
        $copy->save();

        $idMap = [];
        foreach ([Invitation::COLLECTION_LIBRARY, Invitation::COLLECTION_AUDIO] as $collection) {
            foreach ($invitation->getMedia($collection) as $media) {
                $new = $media->copy($copy, $collection);
                $idMap[$media->id] = $new->id;
            }
        }

        $schema = $this->registry->schema($invitation->template->key);
        $copy->settings = $this->remapMediaIds($schema, $invitation->settings ?? [], $idMap);
        $copy->save();

        return $copy->refresh();
    }

    /**
     * Walk the schema and remap media-id values (image/gallery/audio fields only).
     *
     * @param array<string, mixed> $sections
     * @param array<string, mixed> $settings
     * @param array<int, int> $idMap
     * @return array<string, mixed>
     */
    protected function remapMediaIds(array $sections, array $settings, array $idMap): array
    {
        $mapField = function (string $type, $value) use ($idMap) {
            if ($type === 'image' || $type === 'audio') {
                return $idMap[(int) $value] ?? $value;
            }
            if ($type === 'gallery' && is_array($value)) {
                return array_map(fn ($id) => $idMap[(int) $id] ?? $id, $value);
            }

            return $value;
        };

        foreach ($sections as $sectionKey => $sectionDef) {
            foreach ($sectionDef['fields'] ?? [] as $fieldKey => $fieldDef) {
                if (isset($settings[$sectionKey][$fieldKey])) {
                    $settings[$sectionKey][$fieldKey] = $mapField($fieldDef['type'] ?? 'text', $settings[$sectionKey][$fieldKey]);
                }
            }

            $itemFields = $sectionDef['repeater']['item_fields'] ?? null;
            if ($itemFields && ! empty($settings[$sectionKey]['items'])) {
                foreach ($settings[$sectionKey]['items'] as $idx => $item) {
                    foreach ($itemFields as $fieldKey => $fieldDef) {
                        if (isset($item[$fieldKey])) {
                            $settings[$sectionKey]['items'][$idx][$fieldKey] = $mapField($fieldDef['type'] ?? 'text', $item[$fieldKey]);
                        }
                    }
                }
            }
        }

        return $settings;
    }

    /**
     * Generate a unique, URL-safe slug from a base string.
     */
    public function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base) ?: 'thiep-cuoi';
        $candidate = $slug;
        $i = 1;

        while (
            Invitation::withTrashed()
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = $slug.'-'.(++$i);
        }

        return $candidate;
    }
}
