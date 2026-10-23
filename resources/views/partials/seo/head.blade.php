{{--
    Everything search engines and social networks read from a public page's
    <head>. Resolved by SeoHeadBuilder from the page's own SEO (SEO > Page SEO,
    or the Site Page's SEO tab), the view's built-in defaults, and the
    sitewide defaults (SEO > SEO settings). Included from layouts/marketing.
--}}
<?php
    // Inline @section('title', ...) values arrive already HTML-escaped by
    // Blade; decode once so they are escaped exactly once on output.
    $sectionText = fn (string $name) => html_entity_decode($__env->yieldContent($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $seo = app(\App\Services\Seo\SeoHeadBuilder::class)->build([
        'route' => request()->route()?->getName(),
        'page' => $page ?? null,
        'title' => $sectionText('title'),
        'description' => $sectionText('meta_description'),
        'og_image' => $sectionText('og_image'),
        'robots' => $sectionText('robots'),
        'body' => $__env->yieldContent('content').$__env->yieldPushContent('head'),
    ]);
    $gscVerification = \App\Support\Seo\SiteVerificationCode::normalize(\App\Models\SiteSetting::get('seo_gsc_verification'));
    $bingVerification = \App\Support\Seo\SiteVerificationCode::normalize(\App\Models\BingWebmasterSetting::cachedVerificationCode());
?>
<title>{{ $seo['title'] }}</title>
@if ($seo['description'])
    <meta name="description" content="{{ $seo['description'] }}">
@endif
@if ($seo['keywords'])
    <meta name="keywords" content="{{ implode(', ', $seo['keywords']) }}">
@endif
@if ($seo['robots'])
    <meta name="robots" content="{{ $seo['robots'] }}">
@endif
<link rel="canonical" href="{{ $seo['canonical'] }}">
@if ($gscVerification)
    <meta name="google-site-verification" content="{{ $gscVerification }}">
@endif
@if ($bingVerification)
    <meta name="msvalidate.01" content="{{ $bingVerification }}">
@endif
<meta property="og:site_name" content="{{ $seo['site_name'] }}">
<meta property="og:locale" content="en_US">
<meta property="og:type" content="{{ $seo['og']['type'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta property="og:title" content="{{ $seo['og']['title'] }}">
@if ($seo['og']['description'])
    <meta property="og:description" content="{{ $seo['og']['description'] }}">
@endif
@if ($seo['og']['image'])
    <meta property="og:image" content="{{ $seo['og']['image']['url'] }}">
    @if (str_starts_with($seo['og']['image']['url'], 'https://'))
        <meta property="og:image:secure_url" content="{{ $seo['og']['image']['url'] }}">
    @endif
    @if ($seo['og']['image']['type'])
        <meta property="og:image:type" content="{{ $seo['og']['image']['type'] }}">
    @endif
    @if ($seo['og']['image']['width'])
        <meta property="og:image:width" content="{{ $seo['og']['image']['width'] }}">
        <meta property="og:image:height" content="{{ $seo['og']['image']['height'] }}">
    @endif
    <meta property="og:image:alt" content="{{ $seo['og']['title'] }}">
@endif
@if ($seo['og']['type'] === 'article')
    @if ($seo['og']['modified_time'])
        <meta property="article:modified_time" content="{{ $seo['og']['modified_time'] }}">
    @endif
    @foreach ($seo['tags'] as $tag)
        <meta property="article:tag" content="{{ $tag }}">
    @endforeach
@endif
@if ($seo['facebook_app_id'])
    <meta property="fb:app_id" content="{{ $seo['facebook_app_id'] }}">
@endif
<meta name="twitter:card" content="{{ $seo['twitter']['card'] }}">
@if ($seo['twitter']['site'])
    <meta name="twitter:site" content="{{ $seo['twitter']['site'] }}">
@endif
<meta name="twitter:title" content="{{ $seo['twitter']['title'] }}">
@if ($seo['twitter']['description'])
    <meta name="twitter:description" content="{{ $seo['twitter']['description'] }}">
@endif
@if ($seo['twitter']['image'])
    <meta name="twitter:image" content="{{ $seo['twitter']['image']['url'] }}">
    <meta name="twitter:image:alt" content="{{ $seo['twitter']['title'] }}">
@endif
@include('partials.seo.icons')
<script type="application/ld+json">{!! json_encode($seo['json_ld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@if ($seo['custom_json_ld'])
    <script type="application/ld+json">{!! $seo['custom_json_ld'] !!}</script>
@endif
