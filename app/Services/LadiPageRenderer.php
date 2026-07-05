<?php

namespace App\Services;

use App\Models\Invitation;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;

/**
 * Renders a "ladipage" engine template: serves the ORIGINAL LadiPage HTML
 * byte-for-byte (so the design/animations are 100% identical) and injects:
 *   - window.__INV__ config (text/image/music/gallery/forms) for client hydration
 *   - Vite public assets (hydration + interactivity)
 *   - SEO/OG meta, a visibility guard, and the realtime hearts overlay
 *
 * Editable content is applied in the browser by resources/js/public/ladipage.ts,
 * keeping the server HTML untouched.
 */
class LadiPageRenderer
{
    public function __construct(private readonly SettingsResolver $resolver) {}

    /**
     * @param array<string, mixed> $manifest
     */
    public function render(Invitation $invitation, array $manifest): string
    {
        $key = $invitation->template->key;
        $html = File::get(rtrim((string) config('templates.path'), '/')."/{$key}/index.html");

        // Repoint the template's `/templates/...` asset references to S3/CDN so the
        // browser loads images/fonts/media off-server. No-op when asset_url is empty.
        $html = $this->rewriteAssetUrls($html);

        // Strip dead weight LadiPage ships in <head>: legacy IE polyfills and
        // preconnects to form/sales endpoints this app never calls.
        $html = $this->trimHead($html);

        $sections = $manifest['sections'] ?? [];
        $settings = $this->resolver->resolve($sections, $invitation->settings ?? [], $invitation);
        $ladi = $manifest['ladipage'] ?? [];

        $config = $this->buildConfig($settings, $ladi);
        $seo = $this->seo($invitation, $settings);

        // Brand the tab: SEO title + site favicon (replaces the template's own).
        $html = $this->applyBranding($html, $seo);

        // <body ...> -> add data hooks for the realtime/forms layer
        $html = preg_replace(
            '/<body\b/',
            '<body data-slug="'.e($invitation->slug).'" data-invitation="'.$invitation->id.'"',
            $html,
            1
        );

        $html = str_replace('</head>', $this->head($seo).'</head>', $html);
        $html = str_replace('</body>', $this->body($config).'</body>', $html);

        return $html;
    }

    /**
     * Rewrite absolute `/{public_dir}/...` asset references (src="", href="",
     * url(...)) to the configured asset base URL (S3/CDN). Only touches paths that
     * open with a quote or paren so unrelated substrings are never mangled.
     */
    protected function rewriteAssetUrls(string $html): string
    {
        $base = (string) config('templates.asset_url');

        if ($base === '') {
            return $html;
        }

        $dir = trim((string) config('templates.public_dir'), '/');

        return preg_replace_callback(
            '#(["\'(])/'.preg_quote($dir, '#').'/#',
            fn (array $m): string => $m[1].$base.'/'.$dir.'/',
            $html
        );
    }

    /**
     * Brand the browser tab for the public page:
     *  - set the document <title> to the invitation's SEO title (only the first
     *    <title>; the template's inner "Music Player" <title> is left alone)
     *  - remove the template's own favicons (ours is injected via head())
     *
     * @param array<string, mixed> $seo
     */
    protected function applyBranding(string $html, array $seo): string
    {
        $html = preg_replace(
            '#<title>.*?</title>#is',
            '<title>'.e($seo['title']).'</title>',
            $html,
            1
        );

        return preg_replace('#<link[^>]*rel="[^"]*icon[^"]*"[^>]*>#i', '', $html);
    }

    /**
     * Favicon <link>s pointing at the configured site brand.
     */
    protected function faviconLinks(): string
    {
        $url = e(asset((string) config('templates.favicon')));

        return '<link rel="icon" type="image/png" href="'.$url.'">'
            .'<link rel="apple-touch-icon" href="'.$url.'">';
    }

    /**
     * Remove render-blocking dead weight from the LadiPage <head>:
     *  - html5shiv/respond IE polyfills (useless on modern browsers, loaded
     *    unconditionally as blocking third-party scripts)
     *  - preconnect hints to LadiPage form/sales/analytics endpoints that this
     *    app never contacts (it uses its own RSVP/guestbook API)
     */
    protected function trimHead(string $html): string
    {
        $html = preg_replace(
            '#<script[^>]*src="https://w\.ladicdn\.com/[^"]*(?:html5shiv|respond)\.min\.js[^"]*"[^>]*></script>#i',
            '',
            $html
        );

        $html = preg_replace(
            '#<link[^>]*rel="preconnect"[^>]*href="https://(?:api\d*\.ldpform\.com|api\.sales\.ldpform\.net|a\.ladipage\.com)/?"[^>]*>#i',
            '',
            $html
        );

        return $html;
    }

    /**
     * @param array<string, mixed> $settings resolved settings
     * @param array<string, mixed> $ladi      manifest ladipage block
     * @return array<string, mixed>
     */
    protected function buildConfig(array $settings, array $ladi): array
    {
        // text: element-id => value
        $text = [];
        foreach ($ladi['text'] ?? [] as $id => $path) {
            $val = data_get($settings, $path);
            if (is_string($val) && $val !== '') {
                $text[$id] = $val;
            }
        }

        // images: original-filename => new url
        $images = [];
        foreach ($ladi['images'] ?? [] as $filename => $path) {
            $url = data_get($settings, $path.'.web') ?? data_get($settings, $path.'.full');
            if ($url) {
                $images[$filename] = $url;
            }
        }

        $foods = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) data_get($settings, 'rsvp.food_options', ''))
        )));

        return [
            'text' => $text,
            'fit' => $ladi['fit'] ?? [],
            'nav' => $ladi['nav'] ?? [],
            'hide' => $ladi['hide'] ?? [],
            'mapUrl' => data_get($settings, 'invite.map_url')
                ?? data_get($settings, 'events.gai_map_url')
                ?? data_get($settings, 'events.map_url'),
            'images' => $images,
            'music' => data_get($settings, 'music.audio'),
            'gallery' => data_get($settings, 'gallery.images', []),
            'sections' => $ladi['sections'] ?? [],
            'blocks' => [
                'gallery' => [
                    'heading' => data_get($settings, 'gallery.heading', 'Album Ảnh'),
                    'tagline' => data_get($settings, 'gallery.tagline'),
                ],
                'rsvp' => [
                    'enabled' => (bool) data_get($settings, 'rsvp.enabled', true),
                    'heading' => data_get($settings, 'rsvp.heading', 'Xác Nhận Tham Dự'),
                    'intro' => data_get($settings, 'rsvp.intro'),
                    'foods' => $foods,
                ],
                'guestbook' => [
                    'enabled' => (bool) data_get($settings, 'guestbook.enabled', true),
                    'heading' => data_get($settings, 'guestbook.heading', 'Gửi Lời Chúc'),
                    'intro' => data_get($settings, 'guestbook.intro'),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    protected function seo(Invitation $invitation, array $settings): array
    {
        $couple = trim(implode(' & ', array_filter([
            data_get($settings, 'hero.groom_name'),
            data_get($settings, 'hero.bride_name'),
        ])));

        return array_merge([
            'title' => $invitation->title ?: ($couple ?: 'Thiệp cưới'),
            'description' => data_get($settings, 'hero.subtitle', 'Trân trọng kính mời!'),
            'og_image' => data_get($settings, 'hero.background.web'),
        ], array_filter($invitation->seo ?? []));
    }

    /**
     * @param array<string, mixed> $seo
     */
    protected function head(array $seo): string
    {
        $vite = (app(Vite::class))(['resources/css/public.css', 'resources/js/public/ladipage.ts'])->toHtml();

        $og = '';
        $og .= '<meta property="og:title" content="'.e($seo['title']).'">';
        $og .= '<meta property="og:description" content="'.e($seo['description']).'">';
        if (! empty($seo['og_image'])) {
            $og .= '<meta property="og:image" content="'.e($seo['og_image']).'">';
        }
        $og .= '<meta name="twitter:card" content="summary_large_image">';

        // Hide until hydration to avoid a flash of the template's demo content.
        $guard = '<style id="__inv_guard">html{visibility:hidden}</style>';

        // Strip the LadiPage "Powered by" badge the runtime injects.
        $brand = '<style>.ladipage_powered_by{display:none!important}</style>';

        return $this->faviconLinks().$og.$guard.$brand.$vite;
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function body(array $config): string
    {
        $json = json_encode(
            $config,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        // Inline safety reveal: if the Vite hydration bundle fails to load, never
        // leave the page hidden behind the guard — show original content after 2s.
        $safety = '<script>setTimeout(function(){var g=document.getElementById("__inv_guard");'
            .'if(g)g.remove();document.documentElement.style.visibility="";},2000);</script>';

        return '<div class="inv-hearts" id="inv-hearts" aria-hidden="true"></div>'
            .'<script>window.__INV__='.$json.'</script>'.$safety;
    }
}
