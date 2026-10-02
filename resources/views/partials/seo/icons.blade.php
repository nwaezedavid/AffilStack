{{--
    The full favicon set (see FaviconGenerator/FaviconController): served from
    stable root URLs on this host, which is what Google's favicon guidelines
    require. Shared by the marketing, dashboard and guest/auth layouts.
--}}
<?php
    $faviconGenerator = app(\App\Services\Seo\FaviconGenerator::class);
    $faviconAppName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
?>
<link rel="icon" href="{{ $faviconGenerator->url('favicon.ico') }}" sizes="16x16 32x32 48x48">
<link rel="icon" type="image/png" sizes="48x48" href="{{ $faviconGenerator->url('favicon-48x48.png') }}">
<link rel="icon" type="image/png" sizes="96x96" href="{{ $faviconGenerator->url('favicon-96x96.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ $faviconGenerator->url('favicon-192x192.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ $faviconGenerator->url('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ $faviconGenerator->url('site.webmanifest') }}">
<meta name="theme-color" content="{{ $faviconGenerator->themeColor() }}">
<meta name="application-name" content="{{ $faviconAppName }}">
<meta name="apple-mobile-web-app-title" content="{{ $faviconAppName }}">
