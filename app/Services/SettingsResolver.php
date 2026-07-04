<?php

namespace App\Services;

use App\Models\Invitation;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Walks a template's section schema and turns the raw settings JSON into a
 * render-ready tree: media-id references become URL objects, galleries become
 * ordered lists of images, audio becomes a URL. Repeaters resolve recursively.
 */
class SettingsResolver
{
    /**
     * @param array<string, mixed> $sections schema sections
     * @param array<string, mixed> $settings raw invitation settings
     * @return array<string, mixed>
     */
    public function resolve(array $sections, array $settings, Invitation $invitation): array
    {
        $images = $invitation->getMedia(Invitation::COLLECTION_LIBRARY)->keyBy('id');
        $audio = $invitation->getMedia(Invitation::COLLECTION_AUDIO)->keyBy('id');

        $out = [];

        foreach ($sections as $sectionKey => $sectionDef) {
            $raw = $settings[$sectionKey] ?? [];
            $resolved = [];

            foreach ($sectionDef['fields'] ?? [] as $fieldKey => $fieldDef) {
                $resolved[$fieldKey] = $this->resolveField(
                    (string) ($fieldDef['type'] ?? 'text'),
                    $raw[$fieldKey] ?? null,
                    $images,
                    $audio,
                );
            }

            if (isset($sectionDef['repeater']['item_fields'])) {
                $itemFields = $sectionDef['repeater']['item_fields'];
                $resolved['items'] = collect($raw['items'] ?? [])
                    ->map(fn ($item) => $this->resolveItem($itemFields, (array) $item, $images, $audio))
                    ->values()
                    ->all();
            }

            $out[$sectionKey] = $resolved;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $itemFields
     * @param array<string, mixed> $item
     * @param Collection<int, Media> $images
     * @param Collection<int, Media> $audio
     * @return array<string, mixed>
     */
    protected function resolveItem(array $itemFields, array $item, Collection $images, Collection $audio): array
    {
        $resolved = [];
        foreach ($itemFields as $fieldKey => $fieldDef) {
            $resolved[$fieldKey] = $this->resolveField(
                (string) ($fieldDef['type'] ?? 'text'),
                $item[$fieldKey] ?? null,
                $images,
                $audio,
            );
        }

        return $resolved;
    }

    /**
     * @param Collection<int, Media> $images
     * @param Collection<int, Media> $audio
     */
    protected function resolveField(string $type, mixed $value, Collection $images, Collection $audio): mixed
    {
        return match ($type) {
            'image' => $this->resolveImage($value, $images),
            'gallery' => collect(is_array($value) ? $value : [])
                ->map(fn ($id) => $this->resolveImage($id, $images))
                ->filter()
                ->values()
                ->all(),
            'audio' => $this->resolveAudio($value, $audio),
            'boolean' => (bool) $value,
            default => $value,
        };
    }

    /**
     * @param Collection<int, Media> $images
     * @return array<string, mixed>|null
     */
    protected function resolveImage(mixed $id, Collection $images): ?array
    {
        if (! $id || ! ($media = $images->get((int) $id))) {
            return null;
        }

        return [
            'id' => $media->id,
            'thumb' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(),
            'web' => $media->hasGeneratedConversion('web') ? $media->getUrl('web') : $media->getUrl(),
            'full' => $media->getUrl(),
            'alt' => (string) $media->getCustomProperty('alt', ''),
        ];
    }

    /**
     * @param Collection<int, Media> $audio
     */
    protected function resolveAudio(mixed $id, Collection $audio): ?string
    {
        if (! $id || ! ($media = $audio->get((int) $id))) {
            return null;
        }

        return $media->getUrl();
    }
}
