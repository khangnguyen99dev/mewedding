<?php

namespace App\Console\Commands;

use App\Models\Template;
use App\Services\TemplateRegistry;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SyncTemplates extends Command
{
    protected $signature = 'templates:sync {--prune : Disable templates whose folder no longer exists} {--s3 : Also upload template assets to the S3 disk} {--force : Re-upload S3 assets even if unchanged (e.g. to refresh cache headers)}';

    /** Far-future immutable caching — template asset filenames are content-hashed by LadiPage. */
    protected const S3_CACHE_CONTROL = 'public, max-age=31536000, immutable';

    protected $description = 'Discover templates on disk, upsert them into the database, and publish their assets';

    protected int $s3Uploaded = 0;

    protected int $s3Skipped = 0;

    public function handle(TemplateRegistry $registry): int
    {
        $registry->forgetCache();
        $manifests = $registry->scan();

        if ($manifests === []) {
            $this->warn('No templates found in '.config('templates.path'));

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($manifests as $key => $manifest) {
            $thumbnail = isset($manifest['thumbnail'])
                ? config('templates.public_dir')."/{$key}/".$manifest['thumbnail']
                : null;

            Template::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $manifest['name'],
                    'version' => $manifest['version'],
                    'description' => $manifest['description'] ?? null,
                    'thumbnail' => $thumbnail,
                    'status' => 'active',
                    'manifest' => $manifest,
                    'synced_at' => now(),
                ],
            );

            $this->publishAssets($key);

            if ($this->option('s3')) {
                $this->pushAssetsToS3($key);
            }

            $rows[] = [$key, $manifest['name'], $manifest['version'], $manifest['has_entry'] ? 'yes' : 'NO'];
        }

        if ($this->option('prune')) {
            $known = array_keys($manifests);
            $disabled = Template::whereNotIn('key', $known)->update(['status' => 'disabled']);
            if ($disabled > 0) {
                $this->warn("Disabled {$disabled} template(s) with no folder on disk.");
            }
        }

        $this->table(['Key', 'Name', 'Version', 'Has entry view'], $rows);
        $this->info('Synced '.count($rows).' template(s).');

        if ($this->option('s3')) {
            $this->info("S3: uploaded {$this->s3Uploaded} file(s), skipped {$this->s3Skipped} unchanged.");
        }

        return self::SUCCESS;
    }

    /**
     * Upload a template's assets/ directory to the S3 disk under
     * {public_dir}/{key}/..., skipping files already present with the same size.
     */
    protected function pushAssetsToS3(string $key): void
    {
        $source = resource_path("templates/{$key}/assets");

        if (! File::isDirectory($source)) {
            return;
        }

        $disk = $this->s3Disk();
        $dir = trim((string) config('templates.public_dir'), '/');

        foreach (File::allFiles($source) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $target = "{$dir}/{$key}/{$relative}";

            if (! $this->option('force') && $disk->exists($target) && $disk->size($target) === $file->getSize()) {
                $this->s3Skipped++;

                continue;
            }

            $stream = fopen($file->getRealPath(), 'rb');
            $disk->writeStream($target, $stream, [
                'CacheControl' => self::S3_CACHE_CONTROL,
            ]);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $this->s3Uploaded++;
        }
    }

    /**
     * The S3 disk, built with throw=true so upload failures surface as exceptions
     * instead of being silently swallowed (the shared s3 disk uses throw=false).
     */
    protected function s3Disk(): Filesystem
    {
        $name = (string) config('templates.s3_disk', 's3');
        $config = config("filesystems.disks.{$name}");

        return Storage::build(array_merge($config, ['throw' => true]));
    }

    /**
     * Copy a template's assets/ directory into the public path so the browser
     * can load its CSS/JS/fonts/images.
     */
    protected function publishAssets(string $key): void
    {
        $source = resource_path("templates/{$key}/assets");

        if (! File::isDirectory($source)) {
            return;
        }

        $dest = public_path(config('templates.public_dir')."/{$key}");
        File::ensureDirectoryExists($dest);
        File::copyDirectory($source, $dest);
    }
}
