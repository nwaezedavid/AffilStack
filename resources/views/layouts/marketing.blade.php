<?php
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
    $logo = \App\Models\SiteSetting::get('logo_path');
    $favicon = \App\Models\SiteSetting::get('favicon_path');
    $announcement = \App\Models\SiteSetting::get('header_announcement');
    $footerText = \App\Models\SiteSetting::get('footer_text', '© '.date('Y').' '.$siteName.'.');
    $menuItems = \App\Models\SiteSetting::get('menu_items', []);
    $blogUrl = \App\Models\SiteSetting::get('seo_blog_url');
    $metaDescription = trim(($__env->yieldContent('meta_description')) ?: \App\Models\SiteSetting::get('seo_meta_description', ''));
    $ogImage = \App\Models\SiteSetting::get('seo_og_image_path');
    $gaId = \App\Models\SiteSetting::get('seo_ga_id');
    $gscVerification = \App\Models\SiteSetting::get('seo_gsc_verification');
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
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="@yield('title', $siteName)">
    @if ($metaDescription)
        <meta property="og:description" content="{{ $metaDescription }}">
    @endif
    @if ($ogImage)
        <meta property="og:image" content="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($ogImage) }}">
    @endif
    @if ($favicon)
        <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($favicon) }}">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($gaId)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @json($gaId));
        </script>
    @endif
    @stack('head')
</head>
<body class="bg-surface text-ink-900 antialiased">
    @if ($announcement)
        <div class="bg-navy-900 text-white text-xs text-center py-2 px-4">{{ $announcement }}</div>
    @endif
    <header class="border-b border-line">
        <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-display font-semibold text-lg text-navy-900">
                @if ($logo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="{{ $siteName }}" class="h-8 w-auto">
                @else
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-navy-900 text-gold-400 text-sm">{{ strtoupper(substr($siteName, 0, 2)) }}</span>
                    {{ $siteName }}
                @endif
            </a>
            <div class="flex items-center gap-4 text-sm">
                @foreach ($menuItems as $item)
                    <a href="{{ $item['url'] ?? '#' }}" class="text-ink-600 hover:text-ink-900">{{ $item['label'] ?? '' }}</a>
                @endforeach
                @if ($blogUrl)
                    <a href="{{ $blogUrl }}" class="text-ink-600 hover:text-ink-900">Blog</a>
                @endif
                <a href="{{ route('registration.pricing') }}" class="text-ink-600 hover:text-ink-900">Pricing</a>
                <a href="{{ route('help.index') }}" class="text-ink-600 hover:text-ink-900">Help</a>
                <a href="{{ route('login') }}" class="text-ink-600 hover:text-ink-900">Sign in</a>
            </div>
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

    <footer class="border-t border-line py-6 text-center text-xs text-ink-400">
        {{ $footerText }}
    </footer>
</body>
</html>
