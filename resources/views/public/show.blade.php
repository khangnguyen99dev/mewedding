@php
    /** @var \App\Models\Invitation $invitation */
    $primary = $theme['primary'] ?? '#b8860b';
    $secondary = $theme['secondary'] ?? '#6e2f22';
    $bg = $theme['background'] ?? '#fffdf9';
    $textColor = $theme['text'] ?? '#2c2420';
    $fontHeading = $theme['font_heading'] ?? "'Playfair Display', serif";
    $fontBody = $theme['font_body'] ?? "'Be Vietnam Pro', system-ui, sans-serif";

    $autoplay = (bool) ($musicConfig['autoplay'] ?? true);
    $loop = (bool) ($musicConfig['loop'] ?? true);
@endphp
<!DOCTYPE html>
<html lang="{{ $invitation->locale ?? 'vi' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    {{-- Flag JS early so reveal-animations apply without a flash; no-JS users see content. --}}
    <script>document.documentElement.classList.add('js')</script>
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    @if (! empty($seo['og_image']))
        <meta property="og:image" content="{{ $seo['og_image'] }}">
    @endif
    <meta property="og:url" content="{{ $invitation->publicUrl() }}">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text y='14' font-size='14'>💍</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Dancing+Script:wght@500;600;700&display=swap" rel="stylesheet">

    {{-- Theme as CSS custom properties: template CSS consumes these. --}}
    <style>
        :root {
            --inv-primary: {{ $primary }};
            --inv-secondary: {{ $secondary }};
            --inv-bg: {{ $bg }};
            --inv-text: {{ $textColor }};
            --inv-font-heading: {{ $fontHeading }};
            --inv-font-body: {{ $fontBody }};
        }
    </style>

    @vite(['resources/css/public.css'])

    {{-- Template-specific stylesheets (published from the template's assets/) --}}
    @foreach ($assets['css'] as $href)
        <link rel="stylesheet" href="{{ $href }}">
    @endforeach
</head>
<body data-invitation="{{ $invitation->id }}" data-slug="{{ $invitation->slug }}">

    @include($entryView)

    {{-- Floating music player (global) --}}
    @if (! empty($musicUrl))
        <audio data-audio src="{{ $musicUrl }}" @if($loop) loop @endif preload="none"></audio>
        <button type="button" class="inv-music" data-music-toggle data-autoplay="{{ $autoplay ? '1' : '0' }}" aria-label="Bật/tắt nhạc">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3v10.55A4 4 0 1 0 14 17V7h4V3h-6z"/></svg>
        </button>
    @endif

    {{-- Realtime hearts overlay (Phase 6) --}}
    <div class="inv-hearts" id="inv-hearts" aria-hidden="true"></div>

    @vite(['resources/js/public/public.ts'])

    {{-- Template-specific scripts --}}
    @foreach ($assets['js'] as $src)
        <script src="{{ $src }}" defer></script>
    @endforeach
</body>
</html>
