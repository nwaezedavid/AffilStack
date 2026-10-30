@extends('layouts.app')

@section('title', 'Get Started')

@section('content')
    @php($firstName = \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first())

    {{-- Hero --}}
    <section class="relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-navy-950 via-navy-900 to-brand-600 px-5 py-7 text-white shadow-lg sm:px-8 sm:py-9">
        <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-brand-500/30 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-28 left-1/3 h-64 w-64 rounded-full bg-gold-400/20 blur-3xl" aria-hidden="true"></div>
        <div class="relative grid gap-6 lg:grid-cols-[1.3fr_1fr] lg:items-center">
            <div>
                <p class="text-[11px] font-mono uppercase tracking-[0.2em] text-gold-300">Get Started · Welcome{{ $firstName ? ', '.$firstName : '' }}</p>
                <h2 class="mt-2 font-display text-2xl font-semibold leading-tight text-balance sm:text-3xl">Turn any affiliate link into buyers, content and campaigns.</h2>
                <p class="mt-2 max-w-xl text-sm text-white/80 sm:text-base">Enter the product you promote. Our AI researches it, tells you exactly who buys it and where to find them — then every tool below is ready to sell it for you.</p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <x-ui.button href="#research" variant="gold" icon="sparkles">Research a product</x-ui.button>
                    <x-ui.button :href="route('onboarding.restart')" variant="light" icon="play-circle" data-tour-restart>Take the tour</x-ui.button>
                </div>
            </div>
            <ol class="grid gap-2.5" aria-label="How AffilStack works">
                @foreach ([
                    ['magnifying-glass-circle', 'Research', 'AI explains the product, the buyer and where they are.'],
                    ['users', 'Collect buyers', 'Search the keywords on Google Maps & LinkedIn, save prospects with the Chrome extension.'],
                    ['paper-airplane', 'Create & send', 'Generate videos, posts and articles, and email your list in one campaign.'],
                ] as $i => [$icon, $stepTitle, $stepText])
                    <li class="flex items-start gap-3 rounded-2xl bg-white/10 p-3.5 ring-1 ring-white/15 backdrop-blur-sm">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gold-400 text-navy-950">
                            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" />
                        </span>
                        <span>
                            <span class="block text-sm font-semibold">{{ $i + 1 }}. {{ $stepTitle }}</span>
                            <span class="block text-xs text-white/75">{{ $stepText }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Account at a glance --}}
    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4" data-tour="account-stats">
        <x-ui.stat label="Credits left" :value="number_format($balance)" icon="bolt" tone="gold"
                   :hint="$subscription ? 'Renews '.$subscription->current_period_end?->format('M j') : 'Top up anytime'" />
        <x-ui.stat label="Plan" :value="$subscription?->plan?->name ?? 'No plan yet'" icon="credit-card" tone="brand"
                   :hint="$subscription ? 'Active' : 'Choose a plan to unlock tools'" />
        <x-ui.stat label="Products researched" :value="number_format($productCount)" icon="cube" tone="violet" />
        <x-ui.stat label="Prospects in CRM" :value="number_format($contactCount)" icon="users" tone="emerald" />
    </div>

    @include('dashboard.partials.progress')

    @if (! empty($nearLimits))
        @php($limit = $nearLimits[0])
        <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-gold-300 bg-gold-300/15 p-4 sm:flex-row sm:items-center sm:justify-between" data-upgrade-nudge>
            <div class="flex items-start gap-3">
                <x-heroicon-o-arrow-trending-up class="mt-0.5 h-6 w-6 shrink-0 text-gold-600" />
                <div>
                    <p class="font-semibold text-navy-900">You've used {{ $limit['percent'] }}% of your {{ $limit['label'] }} this month</p>
                    <p class="text-sm text-ink-600">AffilStack has already done {{ number_format($progress['total']) }} things for your business. Move up a plan to keep it going without pausing.</p>
                </div>
            </div>
            <x-ui.button variant="brand" :href="route('billing.index')" icon="arrow-up-circle">See plans</x-ui.button>
        </div>
    @endif

    @unless ($subscription)
        <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-gold-300/60 bg-gold-300/10 p-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-ink-900"><strong>You don't have an active plan yet.</strong> Pick one to unlock the content tools and your monthly credits.</p>
            <x-ui.button :href="route('billing.index')" variant="primary" size="sm" icon="credit-card">See plans</x-ui.button>
        </div>
    @endunless

    {{-- The research form --}}
    <div id="research" class="scroll-mt-24 mb-8">
        @include('dashboard.offers.partials.research-form', ['returnTo' => 'dashboard'])
    </div>

    {{-- Results for the most recent product --}}
    <div data-results-slot class="scroll-mt-24 mb-8">
        @if ($latestOffer)
            @include('dashboard.offers.partials.results', ['context' => 'dashboard'])
        @else
            <div data-tour="research-results" class="rounded-3xl border-2 border-dashed border-line bg-white/60 p-6 sm:p-8">
                <div class="mx-auto max-w-2xl text-center">
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-50 to-gold-300/30 text-brand-600">
                        <x-heroicon-o-document-magnifying-glass class="h-7 w-7" />
                    </span>
                    <h3 class="mt-4 font-display text-lg font-semibold text-navy-900">Your research results will appear here</h3>
                    <p class="mt-1 text-sm text-ink-600">As soon as you click <strong>Research with AI</strong>, this space fills with everything you need to start selling:</p>
                </div>
                <div class="mx-auto mt-6 grid max-w-4xl gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['cube', 'What the product does', 'text-brand-600 bg-brand-50'],
                        ['user-group', 'Who needs it & buyer personas', 'text-violet-600 bg-violet-50'],
                        ['map', 'Where to find your buyers', 'text-teal-600 bg-teal-50'],
                        ['map-pin', 'Google Maps keywords to search', 'text-emerald-600 bg-emerald-50'],
                        ['briefcase', 'LinkedIn people & company keywords', 'text-sky-600 bg-sky-50'],
                        ['trophy', 'Your best channel & angle', 'text-amber-600 bg-amber-50'],
                    ] as [$icon, $label, $tone])
                        <div class="flex items-center gap-3 rounded-2xl bg-white p-3 ring-1 ring-line">
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $tone }}">
                                <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" />
                            </span>
                            <span class="text-sm font-medium text-navy-900">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Other recent products --}}
    @if ($offers->count() > 1)
        <section class="mb-4">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-display text-base font-semibold text-navy-900">Your other products</h3>
                <a href="{{ route('offers.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">See all &rarr;</a>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($offers->skip(1) as $other)
                    <a href="{{ route('offers.show', $other) }}" class="group flex min-w-0 items-center gap-3 rounded-2xl border border-line bg-white p-3.5 transition hover:border-brand-500 hover:shadow-md">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-navy-800 to-brand-600 font-display text-sm font-semibold text-white">{{ mb_strtoupper(mb_substr($other->product_name, 0, 2)) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-navy-900">{{ $other->product_name }}</span>
                            <span class="block truncate text-xs text-ink-600">{{ $other->affiliate_network }} · {{ $other->created_at->diffForHumans() }}</span>
                        </span>
                        <x-heroicon-m-chevron-right class="h-4 w-4 text-ink-400 transition group-hover:translate-x-0.5" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection

@push('scripts')
    @include('dashboard.offers.partials.results-script')
@endpush
