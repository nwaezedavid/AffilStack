<?php
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
    $logo = \App\Models\SiteSetting::get('logo_rectangular_path');
    $announcement = \App\Models\SiteSetting::get('header_announcement');
    $footerText = \App\Models\SiteSetting::get('footer_text', '© '.date('Y').' '.$siteName.'.');
    // menu_items round-trips through JSON when persisted (SiteSetting is a
    // plain string key-value store — see BrandSettings::save()), but a
    // Tony (Creative Agent) preview overrides it with the raw array
    // directly (SiteSetting::withPreviewOverrides()), so both shapes have
    // to be accepted here.
    $menuItemsRaw = \App\Models\SiteSetting::get('menu_items', '[]');
    $menuItems = is_array($menuItemsRaw) ? $menuItemsRaw : (json_decode((string) $menuItemsRaw, true) ?: []);
    // Only site-relative or http(s) links ever render — a javascript: or
    // data: URL here would run script on every public page.
    $menuItems = array_values(array_filter($menuItems, fn ($item) => is_array($item) && \App\Support\SafeHtml::isSafeUrl((string) ($item['url'] ?? ''))));
    $blogUrl = \App\Models\SiteSetting::get('seo_blog_url');
    // Every SEO/social/schema tag in <head> is resolved by
    // partials/seo/head (SeoHeadBuilder) — see SEO > Page SEO in the admin.
    $gaId = \App\Models\SiteSetting::get('seo_ga_id');
    $metaPixelId = \App\Models\SiteSetting::get('seo_meta_pixel_id');
    $tiktokPixelId = \App\Models\SiteSetting::get('seo_tiktok_pixel_id');
    // Once a Tag Manager container is connected (Site > Site Analytics),
    // GTM is the single sitewide tracking snippet — GA4 is expected to be
    // configured as a tag INSIDE that container rather than injected twice.
    // The bare gtag.js snippet is only the manual-entry fallback for sites
    // that haven't connected Tag Manager.
    $gtmPublicId = \App\Models\GoogleSiteAnalyticsSetting::cachedGtmPublicId();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.seo.head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.tracking-head')
    @include('partials.analytics-beacon')
    @stack('head')
</head>
<body class="bg-surface text-ink-900 antialiased">
    @include('partials.tracking-body')
    {{-- Announcement bar, skip link and sticky main nav (Content → Homepage). --}}
    @include('partials.marketing.header')

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

    <main id="main" tabindex="-1" class="focus:outline-none">
        @yield('content')
    </main>

    @include('partials.marketing.footer')
</body>
</html>
