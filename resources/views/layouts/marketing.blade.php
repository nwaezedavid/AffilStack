<?php
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
    $logo = \App\Models\SiteSetting::get('logo_rectangular_path');
    $favicon = \App\Models\SiteSetting::get('logo_square_path');
    $announcement = \App\Models\SiteSetting::get('header_announcement');
    $footerText = \App\Models\SiteSetting::get('footer_text', '© '.date('Y').' '.$siteName.'.');
    // menu_items round-trips through JSON when persisted (SiteSetting is a
    // plain string key-value store — see BrandSettings::save()), but a
    // Tony (Creative Agent) preview overrides it with the raw array
    // directly (SiteSetting::withPreviewOverrides()), so both shapes have
    // to be accepted here.
    $menuItemsRaw = \App\Models\SiteSetting::get('menu_items', '[]');
    $menuItems = is_array($menuItemsRaw) ? $menuItemsRaw : (json_decode((string) $menuItemsRaw, true) ?: []);
    $blogUrl = \App\Models\SiteSetting::get('seo_blog_url');
    $metaDescription = trim(($__env->yieldContent('meta_description')) ?: \App\Models\SiteSetting::get('seo_meta_description', ''));
    // A per-page @section('og_image', ...) (see marketing/page.blade.php)
    // already resolves to a full URL; the sitewide default is a bare
    // storage path that still needs wrapping — never both at once, since a
    // duplicate og:image tag's precedence varies by crawler.
    $ogImageOverride = trim($__env->yieldContent('og_image') ?: '');
    $ogImage = $ogImageOverride ?: (($path = \App\Models\SiteSetting::get('seo_og_image_path')) ? \Illuminate\Support\Facades\Storage::disk('public')->url($path) : null);
    $robotsDirective = trim($__env->yieldContent('robots') ?: '');
    $gaId = \App\Models\SiteSetting::get('seo_ga_id');
    $gscVerification = \App\Models\SiteSetting::get('seo_gsc_verification');
    $metaPixelId = \App\Models\SiteSetting::get('seo_meta_pixel_id');
    $tiktokPixelId = \App\Models\SiteSetting::get('seo_tiktok_pixel_id');
    $bingVerification = \App\Models\BingWebmasterSetting::cachedVerificationCode();
    // Once a Tag Manager container is connected (Site > Site Analytics),
    // GTM is the single sitewide tracking snippet — GA4 is expected to be
    // configured as a tag INSIDE that container rather than injected twice.
    // The bare gtag.js snippet below is only the manual-entry fallback for
    // sites that haven't connected Tag Manager.
    $gtmPublicId = \App\Models\GoogleSiteAnalyticsSetting::cachedGtmPublicId();

    // Sitewide Organization + WebSite structured data (every page, not just
    // the homepage's own SoftwareApplication schema) — a Knowledge Panel
    // prerequisite. sameAs only includes profiles an admin actually filled
    // in under Site > SEO, never a fabricated/empty link.
    $sameAs = array_values(array_filter([
        \App\Models\SiteSetting::get('seo_social_facebook'),
        \App\Models\SiteSetting::get('seo_social_twitter'),
        \App\Models\SiteSetting::get('seo_social_linkedin'),
        \App\Models\SiteSetting::get('seo_social_instagram'),
        \App\Models\SiteSetting::get('seo_social_youtube'),
    ]));
    $organizationJsonLd = array_filter([
        '@context' => 'https://schema.org',
        '@graph' => [
            array_filter([
                '@type' => 'Organization',
                'name' => $siteName,
                'url' => route('home'),
                'logo' => $logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($logo) : null,
                'sameAs' => $sameAs ?: null,
            ]),
            [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => route('home'),
            ],
        ],
    ]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $siteName)</title>
    @if ($metaDescription)
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if ($gscVerification)
        <meta name="google-site-verification" content="{{ $gscVerification }}">
    @endif
    @if ($bingVerification)
        <meta name="msvalidate.01" content="{{ $bingVerification }}">
    @endif
    @if ($robotsDirective)
        <meta name="robots" content="{{ $robotsDirective }}">
    @endif
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', $siteName)">
    @if ($metaDescription)
        <meta property="og:description" content="{{ $metaDescription }}">
    @endif
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    <meta name="twitter:title" content="@yield('title', $siteName)">
    @if ($metaDescription)
        <meta name="twitter:description" content="{{ $metaDescription }}">
    @endif
    @if ($favicon)
        <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($favicon) }}">
        <link rel="apple-touch-icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($favicon) }}">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">
    <script type="application/ld+json">{!! json_encode($organizationJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.tracking-head')
    @include('partials.analytics-beacon')
    @stack('head')
</head>
<body class="bg-surface text-ink-900 antialiased">
    @include('partials.tracking-body')
    @if ($announcement)
        <div class="bg-navy-900 text-white text-xs text-center py-2 px-4">{{ $announcement }}</div>
    @endif
    <header class="border-b border-line">
        {{-- Mobile nav toggle — a pure-CSS checkbox/peer pattern (no Alpine/JS
             dependency on this layout), so it degrades to a keyboard- and
             screen-reader-reachable control rather than a `hidden` (removed
             from the tab order) checkbox. See the matching pattern in
             layouts/app.blade.php's sidebar drawer. --}}
        <input type="checkbox" id="marketing-nav-toggle" class="peer sr-only">
        <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-display font-semibold text-lg text-navy-900 shrink-0">
                @if ($logo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="{{ $siteName }}" class="h-8 w-auto">
                @else
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-navy-900 text-gold-400 text-sm">{{ strtoupper(substr($siteName, 0, 2)) }}</span>
                    {{ $siteName }}
                @endif
            </a>

            {{-- Desktop nav (md and up) --}}
            <div class="hidden md:flex items-center gap-4 text-sm">
                @foreach ($menuItems as $item)
                    <a href="{{ $item['url'] ?? '#' }}" class="text-ink-600 hover:text-ink-900">{{ $item['label'] ?? '' }}</a>
                @endforeach
                @if (\App\Models\SiteSetting::flag('affiliate_program_enabled'))
                    <a href="{{ route('affiliate.landing') }}" class="text-ink-600 hover:text-ink-900">Affiliate Program</a>
                @endif
                @if ($blogUrl)
                    <a href="{{ $blogUrl }}" class="text-ink-600 hover:text-ink-900">Blog</a>
                @endif
                <a href="{{ route('tutorials.index') }}" class="text-ink-600 hover:text-ink-900">Learning Centre</a>
                <a href="{{ route('registration.pricing') }}" class="text-ink-600 hover:text-ink-900">Pricing</a>
                <a href="{{ route('help.index') }}" class="text-ink-600 hover:text-ink-900">Help</a>
                <a href="{{ route('login') }}" class="text-ink-600 hover:text-ink-900">Sign in</a>
                <a href="{{ route('registration.pricing') }}" class="rounded-md bg-navy-900 text-white px-4 py-2 text-sm font-medium hover:bg-navy-800 transition">Get started</a>
            </div>

            {{-- Mobile: compact CTA + hamburger (below md) --}}
            <div class="flex md:hidden items-center gap-2 shrink-0">
                <a href="{{ route('registration.pricing') }}" class="rounded-md bg-navy-900 text-white px-3 py-1.5 text-sm font-medium hover:bg-navy-800 transition">Get started</a>
                <label for="marketing-nav-toggle"
                       class="cursor-pointer p-2 -mr-2 rounded-md text-ink-600 hover:bg-surface-muted peer-focus-visible:ring-2 peer-focus-visible:ring-navy-900"
                       aria-label="Toggle menu">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </label>
            </div>
        </div>

        {{-- Mobile dropdown panel --}}
        <div class="hidden peer-checked:flex md:hidden flex-col gap-1 px-6 pb-4 text-sm border-t border-line">
            @foreach ($menuItems as $item)
                <a href="{{ $item['url'] ?? '#' }}" class="text-ink-600 hover:text-ink-900 py-2">{{ $item['label'] ?? '' }}</a>
            @endforeach
            @if (\App\Models\SiteSetting::flag('affiliate_program_enabled'))
                <a href="{{ route('affiliate.landing') }}" class="text-ink-600 hover:text-ink-900 py-2">Affiliate Program</a>
            @endif
            @if ($blogUrl)
                <a href="{{ $blogUrl }}" class="text-ink-600 hover:text-ink-900 py-2">Blog</a>
            @endif
            <a href="{{ route('tutorials.index') }}" class="text-ink-600 hover:text-ink-900 py-2">Learning Centre</a>
            <a href="{{ route('registration.pricing') }}" class="text-ink-600 hover:text-ink-900 py-2">Pricing</a>
            <a href="{{ route('help.index') }}" class="text-ink-600 hover:text-ink-900 py-2">Help</a>
            <a href="{{ route('login') }}" class="text-ink-600 hover:text-ink-900 py-2">Sign in</a>
        </div>
    </header>

    @if (session('success'))
        <div class="max-w-5xl mx-auto px-6 mt-6">
            <div class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md px-4 py-2.5">{{ session('success') }}</div>
        </div>
    @endif
    @if (session('error'))
        <div class="max-w-5xl mx-auto px-6 mt-6">
            <div class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-4 py-2.5">{{ session('error') }}</div>
        </div>
    @endif

    @yield('content')

    <footer class="border-t border-line py-8 text-center text-xs text-ink-400">
        <div class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 mb-4">
            <a href="{{ route('about') }}" class="hover:text-ink-600">About</a>
            <a href="{{ route('contact.show') }}" class="hover:text-ink-600">Contact</a>
            <a href="{{ route('help.index') }}" class="hover:text-ink-600">Help</a>
            <a href="{{ route('tutorials.index') }}" class="hover:text-ink-600">Learning Centre</a>
            <a href="{{ route('terms') }}" class="hover:text-ink-600">Terms</a>
            <a href="{{ route('privacy') }}" class="hover:text-ink-600">Privacy</a>
            <a href="{{ route('refund-policy') }}" class="hover:text-ink-600">Refunds</a>
            <a href="{{ route('cookie-policy') }}" class="hover:text-ink-600">Cookies</a>
        </div>
        <x-social-links class="justify-center mb-4" />
        {{ $footerText }}
    </footer>
</body>
</html>
