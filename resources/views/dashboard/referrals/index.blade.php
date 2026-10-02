@extends('layouts.app')

@section('title', $user->isAffiliateOnly() ? 'Partner Dashboard' : 'Refer & Earn')

@php
    $commissionPercent = number_format(config('referrals.commission_rate') * 100);
    $cookieDays = (int) config('referrals.cookie_days');
    $earnedCents = $totals['pending_cents'] + $totals['approved_cents'] + $totals['paid_cents'];
    $tabs = [
        'overview' => ['label' => 'Overview', 'icon' => 'squares-2x2'],
        'banners' => ['label' => 'Banners', 'icon' => 'photo'],
        'emails' => ['label' => 'Email swipes', 'icon' => 'envelope'],
        'social' => ['label' => 'Social posts', 'icon' => 'megaphone'],
        'links' => ['label' => 'Text links & brand', 'icon' => 'link'],
        'guidelines' => ['label' => 'Guidelines', 'icon' => 'shield-check'],
        'payouts' => ['label' => 'Earnings & payouts', 'icon' => 'banknotes'],
    ];
@endphp

@section('content')
    <x-ui.page-header
        :tone="$user->isAffiliateOnly() ? 'emerald' : 'gold'"
        icon="megaphone"
        eyebrow="Marketing kit"
        :title="'Earn '.$commissionPercent.'% on every payment you refer'"
        :subtitle="'Share AffilStack with ready-made banners, emails and posts. Every link carries your code, a visitor stays yours for '.$cookieDays.' days, and you earn on their first payment and every renewal.'">
        <x-slot:actions>
            <x-ui.copy-button :text="$links['plain']" label="Copy my link" variant="gold" size="md" />
            <x-ui.button variant="light" :href="route('referrals.index', ['tab' => 'banners'])" icon="photo" data-tab-link="banners">Get banners</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Tab bar: real links (?tab=…) so it works without JS; the script below
         switches panels in place and keeps the URL in sync. --}}
    <nav class="-mx-4 sm:mx-0 mb-6 overflow-x-auto px-4 sm:px-0" aria-label="Marketing kit sections">
        <div class="inline-flex min-w-full sm:min-w-0 gap-1 rounded-2xl border border-line bg-surface p-1 shadow-[0_1px_2px_rgba(16,24,40,0.04)]" role="tablist">
            @foreach ($tabs as $key => $item)
                <a href="{{ route('referrals.index', ['tab' => $key]) }}"
                   role="tab"
                   id="tab-{{ $key }}"
                   aria-controls="panel-{{ $key }}"
                   aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                   data-tab="{{ $key }}"
                   class="kit-tab inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-xl px-3.5 py-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 {{ $tab === $key ? 'bg-navy-900 text-white shadow-sm' : 'text-ink-600 hover:bg-surface-muted hover:text-navy-900' }}">
                    <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="h-4 w-4" />
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </nav>

    @foreach (array_keys($tabs) as $key)
        <section id="panel-{{ $key }}" role="tabpanel" aria-labelledby="tab-{{ $key }}" data-panel="{{ $key }}" @if ($tab !== $key) hidden @endif>
            @include('dashboard.referrals.partials.'.$key)
        </section>
    @endforeach
@endsection

@push('scripts')
    <script>
        (function () {
            var tabs = document.querySelectorAll('[data-tab]');
            var panels = document.querySelectorAll('[data-panel]');
            var activeClasses = ['bg-navy-900', 'text-white', 'shadow-sm'];
            var idleClasses = ['text-ink-600', 'hover:bg-surface-muted', 'hover:text-navy-900'];

            function show(name, push) {
                var found = false;
                panels.forEach(function (panel) {
                    var match = panel.getAttribute('data-panel') === name;
                    panel.hidden = !match;
                    found = found || match;
                });
                if (!found) { return; }
                tabs.forEach(function (tab) {
                    var match = tab.getAttribute('data-tab') === name;
                    tab.setAttribute('aria-selected', match ? 'true' : 'false');
                    activeClasses.forEach(function (c) { tab.classList.toggle(c, match); });
                    idleClasses.forEach(function (c) { tab.classList.toggle(c, !match); });
                });
                if (push) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('tab', name);
                    history.replaceState(null, '', url);
                }
            }

            document.addEventListener('click', function (event) {
                var link = event.target.closest('[data-tab], [data-tab-link]');
                if (!link || event.metaKey || event.ctrlKey) { return; }
                event.preventDefault();
                show(link.getAttribute('data-tab') || link.getAttribute('data-tab-link'), true);
                if (link.hasAttribute('data-tab-link')) {
                    document.querySelector('[role=tablist]').scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                var filter = link.getAttribute('data-size-filter');
                if (filter && window.affilstackFilterBanners) { window.affilstackFilterBanners(filter); }
            });
        })();
    </script>
@endpush
