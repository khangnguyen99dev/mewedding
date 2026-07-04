<?php

namespace App\Services;

use App\Models\Invitation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;

/**
 * Turns an Invitation + its Template into a fully-resolved view model and renders
 * the template's Blade. Completely template-agnostic: it only knows the manifest
 * conventions (sections schema, theme, declared assets) — never a specific design.
 */
class InvitationRenderer
{
    public function __construct(
        private readonly TemplateRegistry $registry,
        private readonly SettingsResolver $resolver,
        private readonly LadiPageRenderer $ladipage,
    ) {}

    public function render(Invitation $invitation): string
    {
        $key = $invitation->template->key;
        $manifest = $this->registry->find($key) ?? $invitation->template->manifest ?? [];

        // Pixel-perfect templates render the original LadiPage HTML directly.
        if (($manifest['engine'] ?? 'blade') === 'ladipage') {
            return $this->ladipage->render($invitation, $manifest);
        }

        return $this->view($invitation)->render();
    }

    public function view(Invitation $invitation): View
    {
        return ViewFactory::make('public.show', $this->viewData($invitation));
    }

    /**
     * Assemble every variable the template Blade needs.
     *
     * @return array<string, mixed>
     */
    public function viewData(Invitation $invitation): array
    {
        $key = $invitation->template->key;
        $manifest = $this->registry->find($key) ?? $invitation->template->manifest ?? [];
        $sections = $manifest['sections'] ?? [];

        $settings = $this->resolver->resolve($sections, $invitation->settings ?? [], $invitation);
        $theme = array_merge($manifest['theme'] ?? [], array_filter($invitation->theme ?? []));

        $musicUrl = data_get($settings, 'music.audio');
        $ogImage = data_get($settings, 'hero.background.web')
            ?? data_get($settings, 'couple.groom_photo.web');

        return [
            'invitation' => $invitation,
            'templateKey' => $key,
            'template' => $manifest,
            'settings' => $settings,
            'theme' => $theme,
            'seo' => $this->seo($invitation, $settings, $ogImage),
            'assets' => $this->assets($key, $manifest),
            'musicUrl' => $musicUrl,
            'musicConfig' => [
                'autoplay' => (bool) data_get($settings, 'music.autoplay', true),
                'loop' => (bool) data_get($settings, 'music.loop', true),
            ],
            'entryView' => "templates::{$key}.index",
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array{css: list<string>, js: list<string>}
     */
    protected function assets(string $key, array $manifest): array
    {
        $dir = (string) config('templates.public_dir');
        $resolve = fn (string $f): string => asset("{$dir}/{$key}/".ltrim($f, '/'));

        return [
            'css' => array_map($resolve, $manifest['assets']['css'] ?? []),
            'js' => array_map($resolve, $manifest['assets']['js'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $settings resolved settings
     * @return array<string, mixed>
     */
    protected function seo(Invitation $invitation, array $settings, ?string $ogImage): array
    {
        $groom = data_get($settings, 'hero.groom_name');
        $bride = data_get($settings, 'hero.bride_name');
        $couple = trim(implode(' & ', array_filter([$groom, $bride])));

        return array_merge([
            'title' => $invitation->title ?: ($couple ?: 'Thiệp cưới'),
            'description' => data_get($settings, 'hero.subtitle', 'Trân trọng kính mời!'),
            'og_image' => $ogImage,
        ], array_filter($invitation->seo ?? []));
    }
}
