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
        ['icon' => 'cursor-arrow-rays', 'title' => 'Remove the tool sprawl', 'description' => 'Replace the research tool + writing tool + video tool + CRM + affiliate network stack with one platform.'],
        ['icon' => 'rocket-launch', 'title' => 'Make good content achievable solo', 'description' => 'Give one person the output of a small content team, without hiring one.'],
        ['icon' => 'banknotes', 'title' => 'Pay affiliates on time, every time', 'description' => 'A payout workflow built in from day one, not bolted on as an afterthought.'],
        ['icon' => 'chart-bar', 'title' => 'Keep improving with real usage', 'description' => 'Ship what marketers actually ask for, not what looks good in a demo.'],
    ];
    $goals = $decode(\App\Models\SiteSetting::get('about_goals', '[]'));
    $goals = ! empty($goals) ? $goals : $defaultGoals;

    $defaultWhatWeDo = [
        ['icon' => 'magnifying-glass', 'title' => 'Offer & niche research', 'description' => 'A full research brief on any product or niche in minutes — audience, angles, and objections.'],
        ['icon' => 'pencil-square', 'title' => 'AI-written content', 'description' => 'Blog posts, LinkedIn campaigns, and email sequences, generated from your offer and ready to publish.'],
        ['icon' => 'video-camera', 'title' => 'UGC video', 'description' => 'Turn a script into a human-avatar video without a camera, an actor, or an editor.'],
        ['icon' => 'user-group', 'title' => 'Built-in CRM & tracking', 'description' => 'Every lead, click, and sale tracked automatically in one place.'],
        ['icon' => 'credit-card', 'title' => 'Payments & payouts', 'description' => 'Multi-gateway checkout for your customers, and a real payout workflow for your own affiliates.'],
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
        ['icon' => 'user', 'title' => 'Solo affiliate marketers', 'description' => 'Everything you need to research, publish, and get paid without hiring a team or juggling a dozen logins.'],
        ['icon' => 'user-group', 'title' => 'Growing marketing teams', 'description' => 'Multiple seats, shared offers, and one shared view of what\'s working — without losing track of who did what.'],
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

    // The team grid (Site > Team Members) — empty, and so hidden, until real
    // people are added.
    $teamMembers = \App\Models\TeamMember::visibleList();
    $teamHeading = \App\Models\SiteSetting::get('about_team_heading') ?: 'Meet the team';
    $teamSubheading = \App\Models\SiteSetting::get('about_team_subheading') ?: 'The people building '.$siteName.' and looking after our members every day.';

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
    <section class="relative isolate overflow-hidden bg-navy-950 text-white" aria-labelledby="about-heading">
        <div class="absolute inset-0 -z-10" aria-hidden="true" style="background: radial-gradient(55% 70% at 10% 10%, rgba(36,82,217,.5) 0%, transparent 60%), radial-gradient(45% 55% at 95% 90%, rgba(201,162,74,.3) 0%, transparent 65%);"></div>
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 pt-14 pb-20 sm:px-6 sm:pt-20 lg:grid-cols-2 lg:px-8 lg:pb-24">
            <div class="text-center lg:text-left">
                <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 font-mono text-[11px] uppercase tracking-[0.16em] text-gold-300 ring-1 ring-white/15">
                    <span class="h-1.5 w-1.5 rounded-full bg-gold-400" aria-hidden="true"></span>{{ $heroEyebrow }}
                </p>
                <h1 id="about-heading" class="mt-6 font-display text-4xl font-semibold leading-[1.08] tracking-tight text-balance sm:text-5xl">{{ $heroHeadline }}</h1>
                <p class="mx-auto mt-6 max-w-xl text-base leading-relaxed text-white/75 sm:text-lg lg:mx-0">{{ $heroSubheadline }}</p>
                <div class="mt-9 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start">
                    <a href="{{ route('registration.pricing') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-gold-400 px-7 py-3.5 text-sm font-semibold text-navy-950 transition hover:bg-gold-300 sm:w-auto">
                        See plans &amp; pricing <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" />
                    </a>
                    <a href="{{ route('contact.show') }}" class="inline-flex w-full items-center justify-center rounded-full bg-white/10 px-7 py-3.5 text-sm font-semibold text-white ring-1 ring-white/25 transition hover:bg-white/20 sm:w-auto">Get in touch</a>
                </div>
            </div>
            <div>
                @if ($heroImage)
                    <div class="relative">
                        <div class="absolute -inset-4 rounded-[2rem] bg-gradient-to-tr from-brand-500/40 to-gold-400/40 blur-2xl" aria-hidden="true"></div>
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($heroImage) }}" alt="{{ \App\Models\SiteSetting::get('about_hero_image_alt') ?: 'The '.$siteName.' team' }}" class="relative aspect-[16/10] w-full rounded-3xl object-cover shadow-2xl ring-1 ring-white/15">
                    </div>
                @else
                    <div class="relative grid grid-cols-2 gap-4" aria-hidden="true">
                        @foreach ([['magnifying-glass', 'Research', 'from-brand-500 to-sky-500'], ['user-group', 'Buyers', 'from-emerald-500 to-teal-500'], ['sparkles', 'Campaigns', 'from-violet-500 to-fuchsia-500'], ['banknotes', 'Earnings', 'from-gold-500 to-amber-400']] as [$icon, $label, $gradient])
                            <div class="rounded-3xl bg-white/[0.06] p-6 ring-1 ring-white/10 backdrop-blur {{ $loop->odd ? 'translate-y-6' : '' }}">
                                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-lg {{ $gradient }}">@include('marketing.home.partials.icon', ['icon' => $icon, 'class' => 'h-6 w-6'])</span>
                                <p class="mt-5 font-display text-lg font-semibold">{{ $label }}</p>
                                <p class="mt-1 text-xs text-white/55">One platform, one login.</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Our story --}}
    <section class="bg-white py-20 sm:py-24">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            @if ($storyImage)
                <div class="lg:order-2">
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($storyImage) }}" alt="{{ \App\Models\SiteSetting::get('about_story_image_alt') ?: 'Our story' }}" class="aspect-[4/3] w-full rounded-3xl object-cover shadow-xl ring-1 ring-line">
                </div>
            @endif
            <div class="{{ $storyImage ? 'lg:order-1' : 'lg:col-span-2 mx-auto max-w-3xl' }}">
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-brand-600">Where we come from</p>
                <h2 class="mt-3 font-display text-3xl font-semibold tracking-tight text-navy-900 text-balance sm:text-4xl">Our story</h2>
                <div class="mt-6 space-y-4 text-base leading-relaxed text-ink-600 sm:text-lg">
                    @foreach (explode("\n\n", $story) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Mission & vision --}}
    <section class="bg-surface-muted py-20">
        <div class="mx-auto grid max-w-7xl gap-5 px-4 sm:grid-cols-2 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-navy-900 to-brand-700 p-8 text-white sm:p-10">
                <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 text-gold-300"><x-heroicon-o-flag class="h-6 w-6" aria-hidden="true" /></span>
                <h2 class="mt-6 font-display text-xl font-semibold">Our mission</h2>
                <p class="mt-3 leading-relaxed text-white/80">{{ $mission }}</p>
            </div>
            <div class="relative overflow-hidden rounded-3xl bg-white p-8 ring-1 ring-line sm:p-10">
                <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-amber-100 blur-2xl" aria-hidden="true"></div>
                <span class="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 ring-1 ring-amber-500/25"><x-heroicon-o-eye class="h-6 w-6" aria-hidden="true" /></span>
                <h2 class="relative mt-6 font-display text-xl font-semibold text-navy-900">Our vision</h2>
                <p class="relative mt-3 leading-relaxed text-ink-600">{{ $vision }}</p>
            </div>
        </div>
    </section>

    @php
        $accentTiles = ['bg-brand-50 text-brand-600 ring-brand-500/20', 'bg-emerald-50 text-emerald-600 ring-emerald-500/20', 'bg-violet-50 text-violet-600 ring-violet-500/20', 'bg-amber-50 text-amber-600 ring-amber-500/25', 'bg-rose-50 text-rose-600 ring-rose-500/20', 'bg-sky-50 text-sky-600 ring-sky-500/20'];
    @endphp

    {{-- Goals --}}
    <section class="bg-white py-20 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="font-display text-3xl font-semibold tracking-tight text-navy-900 text-balance sm:text-4xl">What we're working toward</h2>
            </div>
            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($goals as $goal)
                    <div class="rounded-3xl bg-white p-6 ring-1 ring-line">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl ring-1 {{ $accentTiles[$loop->index % count($accentTiles)] }}">@include('marketing.home.partials.icon', ['icon' => $goal['icon'] ?? null, 'class' => 'h-6 w-6'])</span>
                        <h3 class="mt-5 font-display text-lg font-semibold text-navy-900">{{ $goal['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-600">{{ $goal['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- What we do --}}
    <section class="bg-gradient-to-b from-surface-muted to-white py-20 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="font-display text-3xl font-semibold tracking-tight text-navy-900 text-balance sm:text-4xl">What we do</h2>
                <p class="mt-4 text-base text-ink-600 sm:text-lg">Everything an affiliate marketer needs, from first research to final payout.</p>
            </div>
            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($whatWeDo as $item)
                    <div class="rounded-3xl bg-white p-6 ring-1 ring-line">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl ring-1 {{ $accentTiles[$loop->index % count($accentTiles)] }}">@include('marketing.home.partials.icon', ['icon' => $item['icon'] ?? null, 'class' => 'h-6 w-6'])</span>
                        <h3 class="mt-5 font-display text-lg font-semibold text-navy-900">{{ $item['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-600">{{ $item['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- How we work --}}
    <section class="bg-white py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center font-display text-3xl font-semibold tracking-tight text-navy-900 text-balance sm:text-4xl">How we work</h2>
            <ol class="mt-12 grid gap-5 sm:grid-cols-3" role="list">
                @foreach ($howWeWork as $index => $step)
                    <li class="rounded-3xl bg-surface-muted p-6">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-navy-900 font-display font-semibold text-gold-300">{{ $index + 1 }}</span>
                        <h3 class="mt-5 font-display text-lg font-semibold text-navy-900">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-600">{{ $step['description'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Who we serve --}}
    <section class="bg-surface-muted py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center font-display text-3xl font-semibold tracking-tight text-navy-900 text-balance sm:text-4xl">Who we serve</h2>
            <div class="mx-auto mt-12 grid max-w-5xl gap-5 sm:grid-cols-2">
                @foreach ($whoWeServe as $audience)
                    <div class="rounded-3xl bg-white p-8 ring-1 ring-line">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl ring-1 {{ $accentTiles[$loop->index % count($accentTiles)] }}">@include('marketing.home.partials.icon', ['icon' => $audience['icon'] ?? null, 'class' => 'h-6 w-6'])</span>
                        <h3 class="mt-5 font-display text-xl font-semibold text-navy-900">{{ $audience['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-600">{{ $audience['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Founder & team — each hidden until real people are entered (never fabricated). --}}
    @if ($founderName || $teamMembers->isNotEmpty())
        <section id="team" class="relative overflow-hidden bg-white py-20 sm:py-28" aria-label="Our people">
            <div class="pointer-events-none absolute -left-40 top-20 h-96 w-96 rounded-full bg-brand-100/60 blur-3xl" aria-hidden="true"></div>
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                @if ($founderName)
                    <div class="mx-auto max-w-2xl text-center">
                        <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-gold-600">Founder</p>
                        <h2 class="mt-3 font-display text-3xl font-semibold tracking-tight text-navy-900 text-balance sm:text-4xl">Behind {{ $siteName }}</h2>
                    </div>
                    <div class="mx-auto mt-10 grid max-w-5xl items-center gap-8 overflow-hidden rounded-[2rem] bg-gradient-to-br from-navy-900 via-navy-800 to-brand-700 p-6 text-white shadow-[0_30px_60px_-30px_rgba(11,30,61,.6)] sm:p-10 md:grid-cols-[auto_1fr]">
                        @if ($founderPhoto)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($founderPhoto) }}" alt="{{ $founderName }}" class="mx-auto h-40 w-40 rounded-3xl object-cover ring-4 ring-gold-400/60 sm:h-48 sm:w-48">
                        @else
                            <span class="mx-auto flex h-40 w-40 items-center justify-center rounded-3xl bg-white/10 font-display text-5xl font-semibold text-gold-300 ring-4 ring-gold-400/40" aria-hidden="true">{{ strtoupper(substr($founderName, 0, 1)) }}</span>
                        @endif
                        <div class="text-center md:text-left">
                            <h3 class="font-display text-2xl font-semibold">{{ $founderName }}</h3>
                            @if ($founderRole)
                                <p class="mt-1 font-mono text-xs uppercase tracking-[0.16em] text-gold-300">{{ $founderRole }}</p>
                            @endif
                            @if ($founderBio)
                                <p class="mt-4 leading-relaxed text-white/80">{{ $founderBio }}</p>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($teamMembers->isNotEmpty())
                    <div class="mx-auto max-w-2xl text-center {{ $founderName ? 'mt-20' : '' }}">
                        <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-brand-600">Our team</p>
                        <h2 class="mt-3 font-display text-3xl font-semibold tracking-tight text-navy-900 text-balance sm:text-4xl">{{ $teamHeading }}</h2>
                        <p class="mt-4 text-base text-ink-600 sm:text-lg">{{ $teamSubheading }}</p>
                    </div>
                    <ul class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-{{ min(4, max(2, $teamMembers->count())) }}" role="list">
                        @foreach ($teamMembers as $member)
                            <li class="group flex flex-col rounded-3xl bg-white p-6 ring-1 ring-line transition duration-300 hover:-translate-y-1 hover:shadow-[0_24px_48px_-24px_rgba(11,30,61,.35)]">
                                @if ($member->photoUrl())
                                    <img src="{{ $member->photoUrl() }}" alt="{{ $member->name }}, {{ $member->role }}" class="aspect-square w-full rounded-2xl object-cover" loading="lazy">
                                @else
                                    <span class="flex aspect-square w-full items-center justify-center rounded-2xl bg-gradient-to-br from-navy-900 to-brand-600 font-display text-5xl font-semibold text-gold-300" aria-hidden="true">{{ $member->initials() }}</span>
                                @endif
                                <h3 class="mt-5 font-display text-lg font-semibold text-navy-900">{{ $member->name }}</h3>
                                <p class="mt-0.5 font-mono text-[11px] uppercase tracking-[0.14em] text-gold-600">{{ $member->role }}</p>
                                @if ($member->bio)
                                    <p class="mt-3 text-sm leading-relaxed text-ink-600">{{ $member->bio }}</p>
                                @endif
                                @if ($links = $member->profileLinks())
                                    <div class="mt-auto flex gap-2 pt-5">
                                        @foreach ($links as $type => $url)
                                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="flex h-9 w-9 items-center justify-center rounded-full bg-surface-muted text-navy-900 transition hover:bg-navy-900 hover:text-white" aria-label="{{ $member->name }} on {{ ['linkedin' => 'LinkedIn', 'x' => 'X', 'website' => 'their website'][$type] }}">
                                                @if ($type === 'website')
                                                    <x-heroicon-o-globe-alt class="h-4 w-4" aria-hidden="true" />
                                                @else
                                                    <x-ui.brand-icon :name="$type" mono class="h-4 w-4" aria-hidden="true" />
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endif

    {{-- Final CTA --}}
    <section class="bg-white pb-20 pt-4 sm:pb-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="relative isolate overflow-hidden rounded-[2.5rem] bg-navy-950 px-6 py-16 text-center sm:px-16">
                <div class="absolute inset-0 -z-10" aria-hidden="true" style="background: radial-gradient(60% 80% at 50% 0%, rgba(36,82,217,.55) 0%, transparent 70%), radial-gradient(40% 60% at 100% 100%, rgba(201,162,74,.4) 0%, transparent 70%);"></div>
                <h2 class="mx-auto max-w-2xl font-display text-3xl font-semibold tracking-tight text-white text-balance sm:text-4xl">{{ $ctaHeading }}</h2>
                <p class="mx-auto mt-4 max-w-lg text-white/75">{{ $ctaSubtext }}</p>
                <a href="{{ $ctaButtonUrl }}" class="mt-8 inline-flex items-center gap-2 rounded-full bg-gold-400 px-8 py-4 text-sm font-semibold text-navy-950 transition hover:bg-gold-300">
                    {{ $ctaButtonLabel }} <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" />
                </a>
            </div>
        </div>
    </section>
@endsection
