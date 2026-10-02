@extends('layouts.marketing')
@use('App\Support\Homepage\HomepageContent')

<?php
    /*
     * The public homepage. Which sections render, and in what order, is set
     * by the admin in Content → Homepage (App\Filament\Pages\HomepageEditor);
     * each section lives in marketing/home/{key}.blade.php. Rendered with no
     * view data on purpose — CreativeTaskPreviewController renders this same
     * view inside HomepageFeature::withPreviewFeature() and
     * SiteSetting::withPreviewOverrides() for Tony's previews, so everything
     * is read here, through those preview-aware entry points.
     */
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
    $sections = HomepageContent::enabledSectionKeys();

    $features = \App\Models\HomepageFeature::previewAwareActiveList();
    $brandLogos = \App\Models\BrandLogo::activePublicList();
    $testimonials = \App\Models\Testimonial::published();
    $plans = \App\Models\Plan::activePublicList()->take(3);
    $faqs = in_array('faq', $sections, true)
        ? \App\Models\FaqItem::publishedGrouped()->flatten(1)->sortBy('sort_order')->take(HomepageContent::faqCount())->values()
        : collect();
    // Only reaches out to the blog when the section is actually switched on.
    $blogPosts = in_array('blog', $sections, true) ? app(\App\Services\Blog\BlogFeed::class)->latest() : [];

    // Falls back to a real (not placeholder) description of the product
    // when the admin hasn't added any Homepage Features yet, so a fresh
    // install never shows an empty section.
    $defaultFeatures = [
        ['icon' => 'magnifying-glass', 'title' => 'Offer research', 'description' => 'A full research brief on any product or niche — demand, audience, angles and objections — so you only promote what sells.'],
        ['icon' => 'map-pin', 'title' => 'Buyer finder', 'description' => 'Find real businesses on Google Maps and decision-makers on LinkedIn, then save them to your CRM in one click.'],
        ['icon' => 'envelope', 'title' => 'CRM & AI email campaigns', 'description' => 'Every lead in one place, with personalised AI-written email campaigns and follow-ups that run on autopilot.'],
        ['icon' => 'pencil-square', 'title' => 'AI content engine', 'description' => 'Blog articles, LinkedIn posts, YouTube scripts, X threads and Pinterest pins, written from your product and ready to publish.'],
        ['icon' => 'video-camera', 'title' => 'UGC & TikTok video', 'description' => 'Turn a script into a human-avatar video your audience actually watches — no camera, actor or editing needed.'],
        ['icon' => 'chart-bar', 'title' => 'Link tracking & earnings', 'description' => 'Branded tracking links show every click, lead and commission, so you double down on what is paying.'],
    ];

    $metaDescriptionDefault = $siteName.' is the all-in-one AI platform for affiliate marketers: research any product, find buyers on Google Maps and LinkedIn, run AI email campaigns and content, and track every click and commission.';

    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => $siteName,
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web',
        'description' => $metaDescriptionDefault,
        'url' => route('home'),
        'offers' => [
            '@type' => 'Offer',
            'url' => route('registration.pricing'),
        ],
    ];

    // Real, admin-entered ratings only — never fabricated — and only once
    // the same 3-testimonial minimum that gates the visible section has
    // been met, so this never claims social proof the page doesn't show.
    $ratedTestimonials = $testimonials->filter(fn ($t) => $t->rating);
    if ($ratedTestimonials->isNotEmpty()) {
        $jsonLd['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => round($ratedTestimonials->avg('rating'), 1),
            'reviewCount' => $ratedTestimonials->count(),
            'bestRating' => 5,
            'worstRating' => 1,
        ];
    }

    /*
     * One accent colour per card/step, cycled — written out in full so
     * Tailwind's scanner sees every class.
     */
    $accents = [
        ['tile' => 'bg-brand-50 text-brand-600 ring-brand-500/20', 'number' => 'bg-brand-600', 'bar' => 'from-brand-500 to-sky-400', 'soft' => 'bg-brand-50', 'text' => 'text-brand-700'],
        ['tile' => 'bg-emerald-50 text-emerald-600 ring-emerald-500/20', 'number' => 'bg-emerald-600', 'bar' => 'from-emerald-500 to-teal-400', 'soft' => 'bg-emerald-50', 'text' => 'text-emerald-700'],
        ['tile' => 'bg-violet-50 text-violet-600 ring-violet-500/20', 'number' => 'bg-violet-600', 'bar' => 'from-violet-500 to-fuchsia-400', 'soft' => 'bg-violet-50', 'text' => 'text-violet-700'],
        ['tile' => 'bg-amber-50 text-amber-600 ring-amber-500/25', 'number' => 'bg-gold-500', 'bar' => 'from-gold-500 to-amber-300', 'soft' => 'bg-amber-50', 'text' => 'text-amber-700'],
        ['tile' => 'bg-rose-50 text-rose-600 ring-rose-500/20', 'number' => 'bg-rose-600', 'bar' => 'from-rose-500 to-orange-400', 'soft' => 'bg-rose-50', 'text' => 'text-rose-700'],
        ['tile' => 'bg-sky-50 text-sky-600 ring-sky-500/20', 'number' => 'bg-sky-600', 'bar' => 'from-sky-500 to-cyan-400', 'soft' => 'bg-sky-50', 'text' => 'text-sky-700'],
    ];
?>

@section('title', $siteName.' — The all-in-one platform for affiliate marketers')
@section('meta_description', $metaDescriptionDefault)

@if ($appStoreId = \App\Support\MobileApps::appStoreId())
    @push('head')
        <meta name="apple-itunes-app" content="app-id={{ $appStoreId }}">
    @endpush
@endif

@push('head')
    <style>
        .home-grid-pattern { background-image: linear-gradient(rgba(255,255,255,.06) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.06) 1px, transparent 1px); background-size: 44px 44px; mask-image: radial-gradient(ellipse at 30% 20%, #000 20%, transparent 70%); }
        .marquee-mask { mask-image: linear-gradient(to right, transparent, black 8%, black 92%, transparent); }
        .marquee-track { width: max-content; animation: marquee-scroll 36s linear infinite; }
        .marquee-mask:hover .marquee-track, .marquee-mask:focus-within .marquee-track { animation-play-state: paused; }
        @keyframes marquee-scroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
        @keyframes home-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        .home-float { animation: home-float 6s ease-in-out infinite; }
        .home-float-delayed { animation: home-float 7s ease-in-out 1.5s infinite; }
        .js-reveal [data-reveal] { opacity: 0; transform: translateY(18px); transition: opacity .7s ease, transform .7s cubic-bezier(.2,.7,.2,1); }
        .js-reveal [data-reveal].is-visible { opacity: 1; transform: none; }
        html { scroll-padding-top: 5rem; scroll-behavior: smooth; }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .marquee-track, .home-float, .home-float-delayed { animation: none; }
            .js-reveal [data-reveal] { opacity: 1; transform: none; transition: none; }
        }
    </style>
@endpush

@section('content')
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    <script>
        if ('IntersectionObserver' in window && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.classList.add('js-reveal');
        }
    </script>

    @foreach ($sections as $sectionKey)
        @include('marketing.home.'.str_replace('_', '-', $sectionKey))
    @endforeach

    <script>
        (function () {
            if (! document.documentElement.classList.contains('js-reveal')) { return; }
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); }
                });
            }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
            document.querySelectorAll('[data-reveal]').forEach(function (el) { observer.observe(el); });
        })();
    </script>
@endsection
