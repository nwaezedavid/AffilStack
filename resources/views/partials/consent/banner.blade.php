{{--
    Agent 04 — cookie consent banner + preferences dialog, included once per
    layout (marketing, dashboard, guest). Never rendered on /embed/* (those
    routes have no layout) or the Filament admin panel. Both elements start
    hidden; public/js/afs-consent.js decides what to show from the visitor's
    own cookie, so cached pages stay identical for everyone.

    Pass ['context' => 'dashboard'|'guest'] where there is no site footer: a
    small "Cookie settings" button is shown instead of the footer link.
--}}
@php
    $consentContext = $context ?? 'marketing';
    $consentEnabled = \App\Support\Analytics\ConsentSettings::bannerEnabled();
    $consentCategories = \App\Support\Analytics\CookieCatalog::categories();
    $consentPolicyUrl = \App\Support\Analytics\ConsentSettings::policyUrl();
@endphp
@if ($consentEnabled)
    <section id="afs-consent-banner" hidden
             aria-labelledby="afs-consent-title" aria-describedby="afs-consent-text"
             class="fixed inset-x-3 bottom-3 z-[60] sm:inset-x-auto sm:left-6 sm:bottom-6 sm:w-[34rem] print:hidden">
        <div class="relative overflow-hidden rounded-2xl bg-white p-5 shadow-2xl shadow-navy-950/20 ring-1 ring-navy-900/10 sm:p-6">
            <div class="pointer-events-none absolute -right-16 -top-16 h-40 w-40 rounded-full bg-brand-100/70 blur-2xl" aria-hidden="true"></div>
            <div class="relative flex items-start gap-3.5">
                <span class="mt-0.5 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-navy-800 text-white shadow-sm" aria-hidden="true">
                    <x-heroicon-o-shield-check class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h2 id="afs-consent-title" class="font-display text-base font-semibold text-navy-900 sm:text-lg">{{ \App\Support\Analytics\ConsentSettings::title() }}</h2>
                    <p id="afs-consent-text" class="mt-1.5 text-sm leading-relaxed text-ink-600">
                        {{ \App\Support\Analytics\ConsentSettings::text() }}
                        <a href="{{ $consentPolicyUrl }}" class="font-medium text-brand-700 underline decoration-brand-100 underline-offset-2 hover:text-navy-900">Cookie Policy</a>
                    </p>
                </div>
            </div>
            <div class="relative mt-5 grid grid-cols-1 gap-2 sm:grid-cols-3">
                <button type="button" data-afs-action="reject" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">Reject all</button>
                <button type="button" data-afs-action="customise" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-navy-900 ring-1 ring-inset ring-navy-900/80 transition hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">Customise</button>
                <button type="button" data-afs-action="accept" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">Accept all</button>
            </div>
        </div>
    </section>

    <div id="afs-consent-modal" hidden class="fixed inset-0 z-[70] flex items-end justify-center bg-navy-950/60 p-0 backdrop-blur-sm sm:items-center sm:p-6 print:hidden" data-afs-backdrop>
        <div role="dialog" aria-modal="true" aria-labelledby="afs-prefs-title" aria-describedby="afs-prefs-intro"
             class="flex max-h-[92vh] w-full max-w-xl flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl ring-1 ring-navy-900/10 sm:rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-700" aria-hidden="true">
                        <x-heroicon-o-adjustments-horizontal class="h-5 w-5" />
                    </span>
                    <h2 id="afs-prefs-title" class="font-display text-lg font-semibold text-navy-900">Cookie preferences</h2>
                </div>
                <button type="button" data-afs-action="close" class="-mr-1.5 rounded-lg p-1.5 text-ink-400 transition hover:bg-surface-muted hover:text-ink-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500" aria-label="Close cookie preferences">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            <div class="flex-1 space-y-3 overflow-y-auto px-5 py-5 sm:px-6">
                <p id="afs-prefs-intro" class="text-sm leading-relaxed text-ink-600">
                    Choose which cookies we may use. Strictly necessary cookies are always on. Analytics and advertising stay off unless you switch them on, and you can change your mind here at any time.
                    <a href="{{ $consentPolicyUrl }}" class="font-medium text-brand-700 underline underline-offset-2">Read the Cookie Policy</a>.
                </p>

                <div data-afs-signal hidden class="flex gap-2.5 rounded-xl bg-emerald-50 px-3.5 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200">
                    <x-heroicon-o-check-badge class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                    <p>Your browser sent a privacy signal (Global Privacy Control or Do Not Track), so we've switched analytics and advertising off for you. You can still turn them on below if you want to.</p>
                </div>

                @foreach ($consentCategories as $key => $category)
                    <div class="rounded-xl p-4 ring-1 ring-line">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h3 id="afs-cat-{{ $key }}" class="text-sm font-semibold text-navy-900">{{ $category['label'] }}</h3>
                                <p id="afs-cat-{{ $key }}-desc" class="mt-1 text-[13px] leading-relaxed text-ink-600">{{ $category['description'] }}</p>
                            </div>
                            @if ($category['required'])
                                <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200">Always on</span>
                            @else
                                <button type="button" role="switch" aria-checked="false" data-afs-toggle="{{ $key }}"
                                        aria-labelledby="afs-cat-{{ $key }}" aria-describedby="afs-cat-{{ $key }}-desc"
                                        class="group relative inline-flex h-7 w-12 shrink-0 cursor-pointer items-center rounded-full bg-ink-400 transition-colors aria-checked:bg-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                                    <span class="sr-only">Allow {{ strtolower($category['label']) }} cookies</span>
                                    <span class="inline-block h-5 w-5 translate-x-1 rounded-full bg-white shadow transition-transform group-aria-checked:translate-x-6" aria-hidden="true"></span>
                                </button>
                            @endif
                        </div>
                        @if (count($category['cookies']))
                            <details class="group/details mt-3">
                                <summary class="flex cursor-pointer list-none items-center gap-1 text-xs font-medium text-brand-700 hover:text-navy-900 [&::-webkit-details-marker]:hidden">
                                    <x-heroicon-m-chevron-right class="h-4 w-4 transition-transform group-open/details:rotate-90" aria-hidden="true" />
                                    Cookies in this category ({{ count($category['cookies']) }})
                                </summary>
                                <ul class="mt-2 space-y-2">
                                    @foreach ($category['cookies'] as $cookie)
                                        <li class="rounded-lg bg-surface-muted px-3 py-2 text-xs text-ink-600">
                                            <span class="font-mono font-semibold text-navy-900">{{ $cookie['name'] }}</span>
                                            <span class="text-ink-400">· {{ $cookie['provider'] }} · {{ $cookie['duration'] }}</span>
                                            <span class="mt-0.5 block">{{ $cookie['purpose'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @else
                            <p class="mt-3 text-xs text-ink-400">No cookies of this kind are in use right now.</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-2 border-t border-line bg-surface-muted/60 px-5 py-4 sm:grid-cols-3 sm:px-6">
                <button type="button" data-afs-action="reject" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">Reject all</button>
                <button type="button" data-afs-action="save" data-afs-initial-focus class="inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-navy-900 ring-1 ring-inset ring-navy-900/80 transition hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">Save choices</button>
                <button type="button" data-afs-action="accept" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">Accept all</button>
            </div>
        </div>
    </div>

    @if ($consentContext !== 'marketing')
        <button type="button" data-cookie-settings
                class="fixed bottom-3 right-3 z-30 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-xs font-medium text-ink-600 shadow-md ring-1 ring-line backdrop-blur transition hover:text-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 print:hidden">
            <x-heroicon-o-finger-print class="h-4 w-4" aria-hidden="true" />
            <span>Cookie settings</span>
        </button>
    @endif
@endif
@php
    $consentConfig = [
        'enabled' => $consentEnabled,
        'version' => \App\Support\Analytics\ConsentSettings::policyVersion(),
        'cookie' => config('analytics.cookies.consent'),
        'days' => (int) config('analytics.cookies.consent_days'),
        'visitorCookie' => config('analytics.cookies.visitor'),
        'visitorDays' => (int) config('analytics.cookies.visitor_days'),
        'logUrl' => route('consent.store', absolute: false),
    ];
@endphp
<script>
    window.AFS_CONSENT = @json($consentConfig);
</script>
<script src="{{ asset('js/afs-consent.js') }}?v={{ @filemtime(public_path('js/afs-consent.js')) ?: '1' }}" defer></script>
