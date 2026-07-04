<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Scaffolds a new "ladipage" engine template from a raw LadiPage export sitting
 * in _design_source/. It automates the mechanical half of onboarding a template:
 *
 *   1. copy the export's asset folders (images/fonts/js/media/css) into assets/
 *   2. copy index.html, rewriting relative asset URLs -> /templates/{key}/...
 *   3. write skeleton template.json + preview.json (with the reusable
 *      gallery/guestbook/rsvp/music sections pre-filled)
 *
 * The human half — mapping LadiPage element IDs (HEADLINE3, PARAGRAPH1, image
 * filenames) to editable fields in template.json's "ladipage" + "sections" — is
 * left to you, with TODO markers and printed next steps. Run `templates:sync`
 * afterwards to publish assets and register the template.
 */
class MakeTemplate extends Command
{
    protected $signature = 'templates:make
        {key : New template key (folder/slug, e.g. azalea)}
        {--from= : Source folder under _design_source/ (defaults to the key)}
        {--force : Overwrite index.html / assets if they already exist}
        {--sync : Run templates:sync after scaffolding}';

    protected $description = 'Scaffold a new LadiPage template from a _design_source export';

    /** Top-level asset directories a LadiPage export may ship. */
    private const ASSET_DIRS = ['images', 'fonts', 'js', 'media', 'css'];

    public function handle(): int
    {
        $key = (string) $this->argument('key');

        if ($key !== Str::slug($key)) {
            $this->error("Key must be a slug (lowercase, hyphenated). Did you mean: ".Str::slug($key)." ?");

            return self::FAILURE;
        }

        $from = (string) ($this->option('from') ?: $key);
        $source = base_path("_design_source/{$from}");
        $sourceIndex = "{$source}/index.html";

        if (! File::isDirectory($source)) {
            $this->error("Source folder not found: _design_source/{$from}");

            return self::FAILURE;
        }

        if (! File::exists($sourceIndex)) {
            $this->error("No index.html in _design_source/{$from} — is this a LadiPage export?");

            return self::FAILURE;
        }

        $dest = resource_path("templates/{$key}");
        $force = (bool) $this->option('force');

        if (File::exists("{$dest}/index.html") && ! $force) {
            $this->error("Template '{$key}' already has an index.html. Use --force to overwrite (template.json / preview.json are never overwritten).");

            return self::FAILURE;
        }

        File::ensureDirectoryExists("{$dest}/assets");

        // 1. Copy asset directories.
        $copied = [];
        foreach (self::ASSET_DIRS as $dir) {
            if (File::isDirectory("{$source}/{$dir}")) {
                File::copyDirectory("{$source}/{$dir}", "{$dest}/assets/{$dir}");
                $copied[] = $dir;
            }
        }
        $this->info('Copied assets: '.($copied ? implode(', ', $copied) : 'none'));

        // 2. Copy + rewrite index.html.
        $html = (string) File::get($sourceIndex);
        $html = $this->rewriteAssetUrls($html, $key);
        File::put("{$dest}/index.html", $html);
        $this->info("Wrote index.html (asset URLs -> /templates/{$key}/...)");

        // 3. Skeleton manifests (never clobber hand-authored ones).
        $wroteManifest = $this->writeJsonIfAbsent("{$dest}/template.json", $this->skeletonManifest($key), $force);
        $wrotePreview = $this->writeJsonIfAbsent("{$dest}/preview.json", $this->skeletonPreview(), $force);

        $this->newLine();
        $this->components->info("Template '{$key}' scaffolded at resources/templates/{$key}");

        if (! File::exists("{$dest}/assets/images/thumbnail.jpg")) {
            $this->warn('No assets/images/thumbnail.jpg — add one (used as the card thumbnail), or edit "thumbnail" in template.json.');
        }

        $this->line('Next steps:');
        $this->line("  1. Open the export and map LadiPage element IDs -> fields:");
        $this->line("       resources/templates/{$key}/template.json  (\"ladipage\".text / .images / .nav / .sections)");
        if (! $wroteManifest) {
            $this->line('       (template.json already existed — left untouched)');
        }
        $this->line("  2. Fill demo content in preview.json".($wrotePreview ? '' : ' (already existed — left untouched)'));
        $this->line("  3. php artisan templates:sync   # publish assets + register in DB");

        if ($this->option('sync')) {
            $this->newLine();
            $this->call('templates:sync');
        }

        return self::SUCCESS;
    }

    /**
     * Prefix relative LadiPage asset references with the public template path,
     * matching how flowers/nobel were published. og:image (content="...") is
     * intentionally left relative, mirroring the existing templates.
     */
    private function rewriteAssetUrls(string $html, string $key): string
    {
        $dirs = implode('|', self::ASSET_DIRS);
        $prefix = "/templates/{$key}/";

        // url(images/...), url("images/..."), url('images/...')
        $html = preg_replace_callback(
            '#\burl\(\s*(["\']?)(?:'.$dirs.')/#i',
            function (array $m) use ($prefix): string {
                $quote = $m[1];
                $dir = substr($m[0], strlen('url(') + strlen($quote)); // e.g. "images/"

                return 'url('.$quote.$prefix.$dir;
            },
            $html
        ) ?? $html;

        // "images/...", 'images/...'  — but not content="images/..." (og:image)
        $html = preg_replace(
            '#(?<!content=)(["\'])('.$dirs.')/#',
            '$1'.$prefix.'$2/',
            $html
        ) ?? $html;

        return $html;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeJsonIfAbsent(string $path, array $data, bool $force): bool
    {
        if (File::exists($path) && ! $force) {
            return false;
        }

        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function skeletonManifest(string $key): array
    {
        $name = Str::headline($key);

        return [
            'name' => $name,
            'version' => '1.0.0',
            'engine' => 'ladipage',
            'description' => "TODO: mô tả phong cách thiệp {$name}.",
            'thumbnail' => 'images/thumbnail.jpg',
            'ladipage' => [
                // TODO: map the template's gallery/rsvp/guestbook SECTION ids.
                'sections' => ['gallery' => 'SECTION?', 'rsvp' => 'SECTION?', 'guestbook' => 'SECTION?'],
                // TODO: LadiPage element ids to hide (watermarks, popups).
                'hide' => [],
                // TODO: nav menu — [{ "label": "...", "section": "SECTION?" }]
                'nav' => [],
                // TODO: headlines that should auto-fit when text changes.
                'fit' => [],
                // TODO: "HEADLINE3": "hero.groom_name", ...
                'text' => new \stdClass(),
                // TODO: "original-file.jpg": "hero.background", ...
                'images' => new \stdClass(),
            ],
            'theme' => new \stdClass(),
            'theme_schema' => new \stdClass(),
            'sections' => $this->skeletonSections(),
        ];
    }

    /**
     * Editable section schema. hero/invite/couple are stubs to adapt per design;
     * gallery/guestbook/rsvp/music are the reusable blocks shared by every
     * template (identical in flowers + nobel).
     *
     * @return array<string, mixed>
     */
    private function skeletonSections(): array
    {
        return [
            'hero' => [
                'label' => 'Trang bìa',
                'fields' => [
                    'logo' => ['type' => 'image', 'label' => 'Logo / Monogram (ảnh vuông)', 'help' => 'Nên dùng ảnh vuông, nền trong suốt (PNG).'],
                    'groom_name' => ['type' => 'text', 'label' => 'Tên chú rể', 'required' => true, 'max' => 80],
                    'bride_name' => ['type' => 'text', 'label' => 'Tên cô dâu', 'required' => true, 'max' => 80],
                    'date' => ['type' => 'text', 'label' => 'Ngày cưới (hiển thị)', 'max' => 40],
                    'background' => ['type' => 'image', 'label' => 'Ảnh nền trang bìa'],
                ],
            ],
            'invite' => [
                'label' => 'Thiệp mời',
                'fields' => [
                    'intro' => ['type' => 'textarea', 'label' => 'Lời mời', 'max' => 400],
                ],
            ],
            'couple' => [
                'label' => 'Chú Rể & Cô Dâu',
                'fields' => [
                    'groom_name' => ['type' => 'text', 'label' => 'Tên hiển thị', 'group' => 'Chú rể', 'max' => 80],
                    'groom_parents' => ['type' => 'textarea', 'label' => 'Bố / Mẹ (mỗi dòng 1 người)', 'group' => 'Chú rể', 'max' => 200],
                    'groom_photo' => ['type' => 'image', 'label' => 'Ảnh chú rể', 'group' => 'Chú rể'],
                    'bride_name' => ['type' => 'text', 'label' => 'Tên hiển thị', 'group' => 'Cô dâu', 'max' => 80],
                    'bride_parents' => ['type' => 'textarea', 'label' => 'Bố / Mẹ (mỗi dòng 1 người)', 'group' => 'Cô dâu', 'max' => 200],
                    'bride_photo' => ['type' => 'image', 'label' => 'Ảnh cô dâu', 'group' => 'Cô dâu'],
                ],
            ],
            'gallery' => [
                'label' => 'Album ảnh (không giới hạn)',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Tiêu đề', 'default' => 'Album Ảnh', 'max' => 60],
                    'tagline' => ['type' => 'text', 'label' => 'Câu dẫn', 'max' => 200],
                    'images' => ['type' => 'gallery', 'label' => 'Bộ sưu tập ảnh', 'help' => 'Kéo-thả sắp xếp. Không giới hạn số lượng.'],
                ],
            ],
            'guestbook' => [
                'label' => 'Sổ lưu bút',
                'fields' => [
                    'enabled' => ['type' => 'boolean', 'label' => 'Bật sổ lưu bút', 'default' => true],
                    'moderate' => ['type' => 'boolean', 'label' => 'Duyệt lời chúc trước khi hiển thị', 'default' => false],
                    'heading' => ['type' => 'text', 'label' => 'Tiêu đề', 'default' => 'Gửi Lời Chúc Mừng', 'max' => 60],
                    'intro' => ['type' => 'text', 'label' => 'Mô tả', 'max' => 200],
                ],
            ],
            'rsvp' => [
                'label' => 'Xác nhận tham dự',
                'fields' => [
                    'enabled' => ['type' => 'boolean', 'label' => 'Bật form RSVP', 'default' => true],
                    'heading' => ['type' => 'text', 'label' => 'Tiêu đề', 'default' => 'Xác Nhận Tham Dự', 'max' => 60],
                    'intro' => ['type' => 'text', 'label' => 'Mô tả', 'max' => 200],
                    'food_options' => ['type' => 'text', 'label' => 'Lựa chọn món (phân tách bởi dấu phẩy)', 'max' => 200, 'default' => 'Mặn, Chay'],
                ],
            ],
            'music' => [
                'label' => 'Nhạc nền',
                'fields' => [
                    'audio' => ['type' => 'audio', 'label' => 'File nhạc (mp3) — để trống dùng nhạc gốc'],
                    'autoplay' => ['type' => 'boolean', 'label' => 'Tự động phát', 'default' => true],
                    'loop' => ['type' => 'boolean', 'label' => 'Lặp lại', 'default' => true],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function skeletonPreview(): array
    {
        return [
            'settings' => [
                'hero' => ['groom_name' => 'Chú Rể', 'bride_name' => 'Cô Dâu', 'date' => '01 - 01 - 2025'],
                'invite' => ['intro' => 'Trân trọng kính mời tới dự bữa tiệc chung vui cùng gia đình chúng tôi.'],
                'couple' => [
                    'groom_name' => 'Chú Rể',
                    'groom_parents' => "Con ông : ...\nCon bà : ...",
                    'bride_name' => 'Cô Dâu',
                    'bride_parents' => "Con ông : ...\nCon bà : ...",
                ],
                'gallery' => ['heading' => 'Album Ảnh', 'tagline' => 'Những khoảnh khắc đáng nhớ của chúng tôi.', 'images' => []],
                'guestbook' => ['enabled' => true, 'moderate' => false, 'heading' => 'Gửi Lời Chúc Mừng', 'intro' => 'Để lại lời chúc nhé!'],
                'rsvp' => ['enabled' => true, 'heading' => 'Xác Nhận Tham Dự', 'intro' => 'Vui lòng xác nhận để chúng tôi chuẩn bị chu đáo.', 'food_options' => 'Mặn, Chay'],
                'music' => ['autoplay' => true, 'loop' => true],
            ],
            'theme' => new \stdClass(),
        ];
    }
}
