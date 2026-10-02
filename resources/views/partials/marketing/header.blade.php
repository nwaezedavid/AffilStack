<?php
    /*
     * Public site header: skip link, the admin's announcement bar (Content →
     * Homepage → Announcement bar) and the sticky main navigation with an
     * accessible mobile menu. Owned by the homepage/marketing body — the
     * <head> is partials/seo/head.
     */
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
    $logo = \App\Models\SiteSetting::get('logo_rectangular_path');
    $announcement = trim((string) \App\Models\SiteSetting::get('header_announcement', ''));
    $announcementLabel = trim((string) \App\Models\SiteSetting::get('header_announcement_link_label', ''));
    $announcementUrl = trim((string) \App\Models\SiteSetting::get('header_announcement_link_url', ''));
    $announcementUrl = $announcementUrl !== '' && (str_starts_with($announcementUrl, '#') || \App\Support\SafeHtml::isSafeUrl($announcementUrl)) ? $announcementUrl : null;

    // menu_items round-trips through JSON when persisted, but a Tony
    // (Creative Agent) preview overrides it with the raw array directly
    // (SiteSetting::withPreviewOverrides()), so both shapes are accepted.
    // Only site-relative or http(s) links ever render.
    $menuItemsRaw = \App\Models\SiteSetting::get('menu_items', '[]');
    $menuItems = is_array($menuItemsRaw) ? $menuItemsRaw : (json_decode((string) $menuItemsRaw, true) ?: []);
    $menuItems = array_values(array_filter($menuItems, fn ($item) => is_array($item) && \App\Support\SafeHtml::isSafeUrl((string) ($item['url'] ?? ''))));

    $blogUrl = \App\Services\Blog\BlogFeed::baseUrl();
    $onHome = request()->routeIs('home');
    $homeAnchor = fn (string $anchor): string => $onHome ? '#'.$anchor : route('home').'#'.$anchor;

    $links = [
        ['label' => 'Features', 'url' => $homeAnchor('features'), 'active' => false],
        ['label' => 'How it works', 'url' => $homeAnchor('how-it-works'), 'active' => false],
        ['label' => 'Pricing', 'url' => route('registration.pricing'), 'active' => request()->routeIs('registration.pricing')],
        ['label' => 'Learning Centre', 'url' => route('tutorials.index'), 'active' => request()->routeIs('tutorials.*')],
    ];
    if ($blogUrl) {
        $links[] = ['label' => 'Blog', 'url' => $blogUrl, 'active' => false, 'external' => true];
    }
    if (\App\Models\SiteSetting::flag('affiliate_program_enabled')) {
        $links[] = ['label' => 'Affiliate Program', 'url' => route('affiliate.landing'), 'active' => request()->routeIs('affiliate.*')];
    }
    $links[] = ['label' => 'Help', 'url' => route('help.index'), 'active' => request()->routeIs('help.*')];
    foreach ($menuItems as $item) {
        $links[] = ['label' => (string) ($item['label'] ?? ''), 'url' => (string) $item['url'], 'active' => false];
    }
?>
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-full focus:bg-navy-900 focus:px-5 focus:py-2.5 focus:text-sm focus:font-semibold focus:text-white focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-gold-400">
    Skip to content
</a>

@if ($announcement !== '')
    <div class="relative overflow-hidden bg-gradient-to-r from-navy-950 via-brand-700 to-navy-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-center gap-x-3 gap-y-1 flex-wrap px-4 py-2 text-center text-xs sm:text-sm">
            <span class="relative flex h-2 w-2 shrink-0" aria-hidden="true">
                <span class="absolute inline-flex h-full w-full rounded-full bg-gold-400 opacity-75 motion-safe:animate-ping"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-gold-400"></span>
            </span>
            <span>{{ $announcement }}</span>
            @if ($announcementUrl)
                <a href="{{ $announcementUrl }}" class="inline-flex items-center gap-1 font-semibold text-gold-300 underline-offset-4 hover:underline focus:outline-none focus-visible:underline">
                    {{ $announcementLabel ?: 'Learn more' }}
                    <x-heroicon-m-arrow-right class="h-3.5 w-3.5" aria-hidden="true" />
                </a>
            @endif
        </div>
    </div>
@endif

<header class="sticky top-0 z-50 border-b border-line/70 bg-white/85 backdrop-blur-md supports-[backdrop-filter]:bg-white/75" data-site-header>
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5 rounded-md font-display text-lg font-semibold text-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500" aria-label="{{ $siteName }} home">
            @if ($logo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="{{ $siteName }}" class="h-8 w-auto">
            @else
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-navy-900 to-brand-600 text-sm text-gold-300 shadow-sm" aria-hidden="true">{{ strtoupper(substr($siteName, 0, 2)) }}</span>
                <span>{{ $siteName }}</span>
            @endif
        </a>

        <nav class="hidden lg:flex items-center gap-1 text-sm" aria-label="Main">
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}"
                   @if ($link['active']) aria-current="page" @endif
                   @if (! empty($link['external'])) rel="noopener" @endif
                   class="rounded-full px-3 py-2 font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 {{ $link['active'] ? 'bg-brand-50 text-brand-700' : 'text-ink-600 hover:bg-surface-muted hover:text-navy-900' }}">{{ $link['label'] }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ route('login') }}" class="hidden sm:inline-flex rounded-full px-3 py-2 text-sm font-medium text-navy-900 hover:bg-surface-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">Sign in</a>
            <a href="{{ route('registration.pricing') }}" class="inline-flex items-center gap-1.5 rounded-full bg-navy-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-brand-500">
                Get started
                <x-heroicon-m-arrow-right class="hidden sm:block h-4 w-4 text-gold-300" aria-hidden="true" />
            </a>
            <button type="button"
                    class="lg:hidden -mr-1 inline-flex h-10 w-10 items-center justify-center rounded-full text-navy-900 hover:bg-surface-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                    aria-controls="mobile-menu" aria-expanded="false" data-mobile-menu-toggle>
                <span class="sr-only">Open menu</span>
                <x-heroicon-o-bars-3 class="h-6 w-6" data-icon-open aria-hidden="true" />
                <x-heroicon-o-x-mark class="hidden h-6 w-6" data-icon-close aria-hidden="true" />
            </button>
        </div>
    </div>

    <div id="mobile-menu" class="hidden lg:hidden border-t border-line bg-white" data-mobile-menu>
        <nav class="mx-auto max-w-7xl px-4 py-3 sm:px-6" aria-label="Mobile">
            <ul class="grid gap-1">
                @foreach ($links as $link)
                    <li>
                        <a href="{{ $link['url'] }}" @if ($link['active']) aria-current="page" @endif
                           class="flex items-center justify-between rounded-xl px-3 py-3 text-base font-medium {{ $link['active'] ? 'bg-brand-50 text-brand-700' : 'text-navy-900 hover:bg-surface-muted' }}">
                            {{ $link['label'] }}
                            <x-heroicon-m-chevron-right class="h-4 w-4 text-ink-400" aria-hidden="true" />
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-3 grid grid-cols-2 gap-2 border-t border-line pt-3 pb-1">
                <a href="{{ route('login') }}" class="rounded-full px-4 py-2.5 text-center text-sm font-semibold text-navy-900 ring-1 ring-line hover:bg-surface-muted">Sign in</a>
                <a href="{{ route('registration.pricing') }}" class="rounded-full bg-gold-400 px-4 py-2.5 text-center text-sm font-semibold text-navy-950 hover:bg-gold-300">See plans</a>
            </div>
        </nav>
    </div>
</header>

<script>
    (function () {
        var toggle = document.querySelector('[data-mobile-menu-toggle]');
        var menu = document.querySelector('[data-mobile-menu]');
        if (! toggle || ! menu) { return; }
        var setOpen = function (open) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.querySelector('.sr-only').textContent = open ? 'Close menu' : 'Open menu';
            toggle.querySelector('[data-icon-open]').classList.toggle('hidden', open);
            toggle.querySelector('[data-icon-close]').classList.toggle('hidden', ! open);
            menu.classList.toggle('hidden', ! open);
        };
        toggle.addEventListener('click', function () { setOpen(toggle.getAttribute('aria-expanded') !== 'true'); });
        menu.addEventListener('click', function (event) { if (event.target.closest('a')) { setOpen(false); } });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') { setOpen(false); toggle.focus(); }
        });
    })();
</script>
