<?php

namespace App\Console\Commands;

use App\Models\Template;
use App\Services\TemplateRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncTemplates extends Command
{
    protected $signature = 'templates:sync {--prune : Disable templates whose folder no longer exists}';

    protected $description = 'Discover templates on disk, upsert them into the database, and publish their assets';

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

        return self::SUCCESS;
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
