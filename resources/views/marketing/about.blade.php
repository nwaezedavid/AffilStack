@extends('layouts.marketing')

<?php
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
    $decode = fn ($raw) => is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

    $heroEyebrow = \App\Models\SiteSetting::get('about_hero_eyebrow') ?: 'About us';
    $heroHeadline = \App\Models\SiteSetting::get('about_hero_headline') ?: "We started {$siteName} to make affiliate marketing possible for one person, not just an agency.";
    $heroSubheadline = \App\Models\SiteSetting::get('about_hero_subheadline') ?: 'A single platform for research, content, and payouts — built for creators and marketers finding their next offer, not enterprise teams with a dozen tools already.';
    $heroImage = \App\Models\SiteSetting::get('about_hero_image_path');

    $story = \App\Models\SiteSetting::get('about_story') ?: "{$siteName} exists because publishing an offer used to mean stitching together a research tool, a writing tool, a video tool, a CRM, and a separate affiliate network — each with its own login, its own learning curve, and its own bill.\n\nWe built one platform instead: tell it what you're promoting, and it researches the offer, writes the content, and tracks every click and sale from a single dashboard. No agency, no dev team, and no dozen subscriptions required to compete with people who have both.";
    $storyImage = \App\Models\SiteSetting::get('about_story_image_path');

    $mission = \App\Models\SiteSetting::get('about_mission') ?: 'To give any individual marketer the same research, content, and tracking capability that used to require a full team — so a good offer and real effort are all it takes to compete.';
    $vision = \App\Models\SiteSetting::get('about_vision') ?: 'A world where starting an affiliate business takes an afternoon, not a stack of tools, tutorials, and trial and error.';

    $defaultGoals = [
        ['icon' => '🎯', 'title' => 'Remove the tool sprawl', 'description' => 'Replace the research tool + writing tool + video tool + CRM + affiliate network stack with one platform.'],
        ['icon' => '🚀', 'title' => 'Make good content achievable solo', 'description' => 'Give one person the output of a small content team, without hiring one.'],
        ['icon' => '🤝', 'title' => 'Pay affiliates on time, every time', 'description' => 'A payout workflow built in from day one, not bolted on as an afterthought.'],
        ['icon' => '📈', 'title' => 'Keep improving with real usage', 'description' => 'Ship what marketers actually ask for, not what looks good in a demo.'],
    ];
    $goals = $decode(\App\Models\SiteSetting::get('about_goals', '[]'));
    $goals = ! empty($goals) ? $goals : $defaultGoals;

    $defaultWhatWeDo = [
        ['icon' => '🔎', 'title' => 'Offer & niche research', 'description' => 'A full research brief on any product or niche in minutes — audience, angles, and objections.'],
        ['icon' => '✍️', 'title' => 'AI-written content', 'description' => 'Blog posts, LinkedIn campaigns, and email sequences, generated from your offer and ready to publish.'],
        ['icon' => '🎬', 'title' => 'UGC video', 'description' => 'Turn a script into a human-avatar video without a camera, an actor, or an editor.'],
        ['icon' => '📇', 'title' => 'Built-in CRM & tracking', 'description' => 'Every lead, click, and sale tracked automatically in one place.'],
        ['icon' => '💳', 'title' => 'Payments & payouts', 'description' => 'Multi-gateway checkout for your customers, and a real payout workflow for your own affiliates.'],
    ];
    $whatWeDo = $decode(\App\Models\SiteSetting::get('about_what_we_do', '[]'));
    $whatWeDo = ! empty($whatWeDo) ? $whatWeDo : $defaultWhatWeDo;

    $defaultHowWeWork = [
        ['title' => 'You tell us what you\'re promoting', 'description' => 'A product name, an affiliate link, or just a niche — that\'s the only input we need to get moving.'],
        ['title' => 'We do the research and the writing', 'description' => 'Offer research, content, and video are generated and queued for your review, not published blind.'],
        ['title' => 'You publish, track, and get paid', 'description' => 'Every click and sale is tracked automatically, and payouts — yours and your affiliates\' — are handled inside the platform.'],
    ];
    $howWeWork = $decode(\App\Models\SiteSetting::get('about_how_we_works', '[]'));
    $howWeWork = ! empty($howWeWork) ? $howWeWork : $defaultHowWeWork;

    $defaultWhoWeServe = [
        ['icon' => '👤', 'title' => 'Solo affiliate marketers', 'description' => 'Everything you need to research, publish, and get paid without hiring a team or juggling a dozen logins.'],
        ['icon' => '👥', 'title' => 'Growing marketing teams', 'description' => 'Multiple seats, shared offers, and one shared view of what\'s working — without losing track of who did what.'],
    ];
    $whoWeServe = $decode(\App\Models\SiteSetting::get('about_who_we_serve', '[]'));
    $whoWeServe = ! empty($whoWeServe) ? $whoWeServe : $defaultWhoWeServe;

    // Never fabricated — the founder section only appears once a real name
    // has been entered in Site > About Page, the same "hide until real"
    // rule Testimonials/Brand Logos already follow.
    $founderName = \App\Models\SiteSetting::get('about_founder_name');
    $founderRole = \App\Models\SiteSetting::get('about_founder_role');
    $founderBio = \App\Models\SiteSetting::get('about_founder_bio');
    $founderPhoto = \App\Models\SiteSetting::get('about_founder_photo_path');

    $ctaHeading = \App\Models\SiteSetting::get('about_cta_heading') ?: 'Ready to build with us?';
    $ctaSubtext = \App\Models\SiteSetting::get('about_cta_subtext') ?: 'Pick a plan and be researching your first offer in minutes.';
    $ctaButtonLabel = \App\Models\SiteSetting::get('about_cta_button_label') ?: 'See plans & pricing';
    $ctaButtonUrl = \App\Models\SiteSetting::get('about_cta_button_url') ?: route('registration.pricing');

    $metaDescriptionDefault = "About {$siteName} — our mission, our founder, and why we built one platform for affiliate research, content, and payouts.";
?>

@section('title', ($page?->displayTitle() ?: 'About us').' — '.$siteName)
@section('meta_description', $page?->meta_description ?: $metaDescriptionDefault)
@if ($page?->og_image_path)
    @section('og_image', \Illuminate\Support\Facades\Storage::disk('public')->url($page->og_image_path))
@endif
@if ($page?->no_index)
    @section('robots', 'noindex, follow')
@endif

@section('content')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'AboutPage',
        'name' => 'About '.$siteName,
        'url' => route('about'),
        'description' => $metaDescriptionDefault,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>

    {{-- Hero --}}
    <section class="max-w-5xl mx-auto px-6 pt-14 pb-16 sm:pt-20 sm:pb-24">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="text-center lg:text-left">
                <span class="inline-block text-xs font-semibold uppercase tracking-wide text-gold-600 mb-3">{{ $heroEyebrow }}</span>
                <h1 class="font-display font-semibold text-4xl sm:text-5xl text-navy-900 leading-tight text-wrap-balance">
                    {{ $heroHeadline }}
                </h1>
                <p class="text-ink-600 mt-5 max-w-xl mx-auto lg:mx-0">{{ $heroSubheadline }}</p>
                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-3">
                    <a href="{{ route('registration.pricing') }}" class="w-full sm:w-auto rounded-md bg-navy-900 text-white px-6 py-3 text-sm font-medium hover:bg-navy-800 transition text-center">
                        See plans &amp; pricing
                    </a>
                    <a href="{{ route('contact.show') }}" class="w-full sm:w-auto rounded-md border border-line px-6 py-3 text-sm font-medium text-navy-900 hover:bg-surface-muted transition text-center">
                        Get in touch
                    </a>
                </div>
            </div>

            <div>
                @if ($heroImage)
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($heroImage) }}"
                        alt="{{ $siteName }}"
                        class="w-full rounded-xl border border-line shadow-sm object-cover aspect-video"
                    >
                @else
                    <div class="w-full aspect-video rounded-xl bg-gradient-to-br from-navy-900 to-navy-700 flex flex-col items-center justify-center gap-4 px-6 text-center relative overflow-hidden">
                        <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 20px 20px;" aria-hidden="true"></div>
                        <span class="relative h-14 w-14 rounded-full bg-white/10 flex items-center justify-center text-2xl" aria-hidden="true">🏢</span>
                        <p class="relative text-white/70 text-sm max-w-xs">Built by marketers, for marketers.</p>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Our story --}}
    <section class="border-y border-line bg-surface-muted">
        <div class="max-w-5xl mx-auto px-6 py-16">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                <div class="{{ $storyImage ? 'lg:order-2' : '' }}">
                    @if ($storyImage)
                        <img
                            src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($storyImage) }}"
                            alt="Our story"
                            class="w-full rounded-xl border border-line shadow-sm object-cover aspect-[4/3]"
                        >
                    @endif
                </div>
                <div class="{{ $storyImage ? 'lg:order-1' : '' }}">
                    <h2 class="font-display font-semibold text-2xl sm:text-3xl text-navy-900 text-wrap-balance mb-4">Our story</h2>
                    <div class="text-sm sm:text-base text-ink-600 leading-relaxed space-y-4">
                        @foreach (explode("\n\n", $story) as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Mission & vision --}}
    <section class="max-w-5xl mx-auto px-6 py-16">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="border border-line rounded-lg p-6 sm:p-8 bg-surface">
                <div class="text-2xl mb-3" aria-hidden="true">🧭</div>
                <h2 class="font-display font-semibold text-lg text-navy-900 mb-2">Our mission</h2>
                <p class="text-ink-600 text-sm leading-relaxed">{{ $mission }}</p>
            </div>
            <div class="border border-line rounded-lg p-6 sm:p-8 bg-surface">
                <div class="text-2xl mb-3" aria-hidden="true">🔭</div>
                <h2 class="font-display font-semibold text-lg text-navy-900 mb-2">Our vision</h2>
                <p class="text-ink-600 text-sm leading-relaxed">{{ $vision }}</p>
            </div>
        </div>
    </section>

    {{-- Goals --}}
    <section class="bg-surface-muted border-y border-line">
        <div class="max-w-5xl mx-auto px-6 py-16">
            <div class="text-center max-w-2xl mx-auto mb-10">
                <h2 class="font-display font-semibold text-2xl sm:text-3xl text-navy-900 text-wrap-balance">What we're working toward</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-sm">
                @foreach ($goals as $goal)
                    <div class="border border-line rounded-lg bg-surface p-5">
                        @if (! empty($goal['icon']))
                            <div class="text-2xl mb-2" aria-hidden="true">{{ $goal['icon'] }}</div>
                        @endif
                        <h3 class="font-display font-semibold text-navy-900 mb-1">{{ $goal['title'] }}</h3>
                        <p class="text-ink-600">{{ $goal['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- What we do --}}
    <section class="max-w-5xl mx-auto px-6 py-16">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <h2 class="font-display font-semibold text-2xl sm:text-3xl text-navy-900 text-wrap-balance">What we do</h2>
            <p class="text-ink-600 mt-3">Everything an affiliate marketer needs, from first research to final payout.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 text-sm">
            @foreach ($whatWeDo as $item)
                <div class="border border-line rounded-lg bg-surface p-5">
                    @if (! empty($item['icon']))
                        <div class="text-2xl mb-2" aria-hidden="true">{{ $item['icon'] }}</div>
                    @endif
                    <h3 class="font-display font-semibold text-navy-900 mb-1">{{ $item['title'] }}</h3>
                    <p class="text-ink-600">{{ $item['description'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- How we work --}}
    <section class="bg-surface-muted border-y border-line">
        <div class="max-w-5xl mx-auto px-6 py-16">
            <h2 class="font-display font-semibold text-2xl sm:text-3xl text-navy-900 text-center text-wrap-balance">How we work</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-8 mt-10 text-sm text-center">
                @foreach ($howWeWork as $index => $step)
                    <div>
                        <div class="mx-auto h-10 w-10 rounded-full bg-navy-900 text-white font-display font-semibold flex items-center justify-center mb-3">{{ $index + 1 }}</div>
                        <h3 class="font-display font-semibold text-navy-900 mb-1">{{ $step['title'] }}</h3>
                        <p class="text-ink-600">{{ $step['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Who we serve --}}
    <section class="max-w-5xl mx-auto px-6 py-16">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <h2 class="font-display font-semibold text-2xl sm:text-3xl text-navy-900 text-wrap-balance">Who we serve</h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            @foreach ($whoWeServe as $audience)
                <div class="border border-line rounded-lg p-6 bg-surface">
                    @if (! empty($audience['icon']))
                        <div class="text-2xl mb-2" aria-hidden="true">{{ $audience['icon'] }}</div>
                    @endif
                    <h2 class="font-display font-semibold text-lg text-navy-900 mb-2">{{ $audience['title'] }}</h2>
                    <p class="text-ink-600 text-sm">{{ $audience['description'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Founder — hidden entirely until a real name is entered (never fabricated). --}}
    @if ($founderName)
        <section class="bg-surface-muted border-y border-line">
            <div class="max-w-4xl mx-auto px-6 py-16">
                <div class="text-center max-w-xl mx-auto mb-10">
                    <h2 class="font-display font-semibold text-2xl sm:text-3xl text-navy-900 text-wrap-balance">Behind {{ $siteName }}</h2>
                </div>
                <div class="border border-line rounded-lg bg-surface p-6 sm:p-8 flex flex-col sm:flex-row items-center sm:items-start gap-6 text-center sm:text-left">
                    @if ($founderPhoto)
                        <img
                            src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($founderPhoto) }}"
                            alt="{{ $founderName }}"
                            class="h-24 w-24 rounded-full object-cover border border-line shrink-0"
                        >
                    @else
                        <span class="h-24 w-24 rounded-full bg-navy-900 text-white flex items-center justify-center text-2xl font-display font-semibold shrink-0" aria-hidden="true">
                            {{ strtoupper(substr($founderName, 0, 1)) }}
                        </span>
                    @endif
                    <div>
                        <h3 class="font-display font-semibold text-lg text-navy-900">{{ $founderName }}</h3>
                        @if ($founderRole)
                            <p class="text-xs text-gold-600 font-semibold uppercase tracking-wide mt-0.5 mb-3">{{ $founderRole }}</p>
                        @endif
                        @if ($founderBio)
                            <p class="text-sm text-ink-600 leading-relaxed">{{ $founderBio }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Final CTA --}}
    <section class="max-w-5xl mx-auto px-6 py-20 text-center">
        <div class="rounded-xl bg-navy-900 px-8 py-12 sm:py-16">
            <h2 class="font-display font-semibold text-2xl sm:text-3xl text-white text-wrap-balance">{{ $ctaHeading }}</h2>
            <p class="text-white/70 mt-3 max-w-lg mx-auto">{{ $ctaSubtext }}</p>
            <a href="{{ $ctaButtonUrl }}" class="inline-block mt-6 rounded-md bg-gold-500 text-navy-900 px-6 py-3 text-sm font-semibold hover:bg-gold-400 transition">
                {{ $ctaButtonLabel }}
            </a>
        </div>
    </section>
@endsection
