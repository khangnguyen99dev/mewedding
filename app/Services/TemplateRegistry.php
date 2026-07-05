<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Discovers templates from the filesystem (resources/templates/{key}) and exposes
 * their manifests. The backend never hardcodes a template: dropping in a folder
 * with a template.json + running `templates:sync` is enough to register a new one.
 */
class TemplateRegistry
{
    /**
     * All template manifests, keyed by template key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $ttl = config('templates.cache_ttl');
        $key = config('templates.cache_key');

        if ($ttl === null) {
            return Cache::rememberForever($key, fn () => $this->scan());
        }

        return Cache::remember($key, (int) $ttl, fn () => $this->scan());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function exists(string $key): bool
    {
        return $this->find($key) !== null;
    }

    /**
     * Section schema for a template (drives the dynamic form + validation).
     *
     * @return array<string, mixed>
     */
    public function schema(string $key): array
    {
        return $this->find($key)['sections'] ?? [];
    }

    /**
     * Demo/sample content shipped with a template (preview.json).
     *
     * @return array<string, mixed>
     */
    public function preview(string $key): array
    {
        $path = $this->templatePath($key).'/preview.json';

        if (! File::exists($path)) {
            return [];
        }

        return $this->readJson($path);
    }

    public function templatePath(string $key): string
    {
        return rtrim((string) config('templates.path'), '/').'/'.$key;
    }

    public function forgetCache(): void
    {
        Cache::forget((string) config('templates.cache_key'));
    }

    /**
     * Read every template folder from disk and parse its manifest.
     *
     * @return array<string, array<string, mixed>>
     */
    public function scan(): array
    {
        $base = (string) config('templates.path');

        if (! File::isDirectory($base)) {
            return [];
        }

        $templates = [];

        foreach (File::directories($base) as $dir) {
            $key = basename($dir);
            $manifestPath = $dir.'/template.json';

            if (! File::exists($manifestPath)) {
                continue;
            }

            $manifest = $this->readJson($manifestPath);
            $manifest['key'] = $key;
            $manifest['name'] ??= ucfirst($key);
            $manifest['version'] ??= '1.0.0';
            $manifest['sections'] ??= [];
            $manifest['theme'] ??= [];
            $manifest['has_entry'] = File::exists($dir.'/index.blade.php') || File::exists($dir.'/index.html');
            $manifest['thumbnail_url'] = $this->thumbnailUrl($key, $manifest['thumbnail'] ?? null);

            // LadiPage templates: expose every image in the HTML as a replaceable
            // field so the couple can swap any picture (slider, backgrounds, ...).
            if (($manifest['engine'] ?? 'blade') === 'ladipage') {
                if ($section = $this->imageSection($key)) {
                    $manifest['sections'] = array_merge($manifest['sections'], ['custom_images' => $section]);
                }
            }

            $templates[$key] = $manifest;
        }

        ksort($templates);

        return $templates;
    }

    /**
     * @return array<string, mixed>
     */
    protected function readJson(string $path): array
    {
        $decoded = json_decode((string) File::get($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Invalid JSON in template manifest: {$path}");
        }

        return $decoded;
    }

    /**
     * Build a synthetic "custom_images" section: one image field per unique image
     * referenced in the template HTML. `match` is the path the client hydration
     * uses to find-and-replace the image; `original` is the shipped picture shown
     * in the editor as the current value.
     *
     * @return array<string, mixed>|null
     */
    protected function imageSection(string $key): ?array
    {
        $images = $this->scanImages($key);

        if ($images === []) {
            return null;
        }

        $fields = [];
        foreach ($images as $img) {
            $fields[$img['field']] = [
                'type' => 'image',
                'label' => $img['name'],
                'original' => $img['url'],
                'match' => $img['match'],
            ];
        }

        return [
            'label' => 'Ảnh trong mẫu',
            'ui' => 'image_grid',
            'fields' => $fields,
        ];
    }

    /**
     * Scan a template's index.html for every unique image it references.
     *
     * @return list<array{field: string, name: string, match: string, url: string}>
     */
    public function scanImages(string $key): array
    {
        $path = $this->templatePath($key).'/index.html';

        if (! File::exists($path)) {
            return [];
        }

        preg_match_all(
            '#templates/'.preg_quote($key, '#').'/images/[^"\'\s)]+?\.(?:png|jpe?g|gif|webp)#i',
            File::get($path),
            $matches,
        );

        $base = (string) config('templates.asset_url');
        $seen = [];
        $out = [];

        foreach ($matches[0] as $ref) {
            $rel = ltrim($ref, '/'); // templates/{key}/images/xxx.jpg

            if (isset($seen[$rel])) {
                continue;
            }
            $seen[$rel] = true;

            $out[] = [
                'field' => 'img_'.substr(md5($rel), 0, 12),
                'name' => basename($rel),
                'match' => $rel,
                'url' => $base !== '' ? $base.'/'.$rel : asset($rel),
            ];
        }

        return $out;
    }

    protected function thumbnailUrl(string $key, ?string $thumbnail): ?string
    {
        if (! $thumbnail) {
            return null;
        }

        $dir = (string) config('templates.public_dir');
        $path = "{$dir}/{$key}/{$thumbnail}";
        $base = (string) config('templates.asset_url');

        return $base !== '' ? "{$base}/{$path}" : asset($path);
    }
}
