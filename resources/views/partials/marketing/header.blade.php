@use('App\Support\Website\NavigationMenu')
<?php
    /*
     * Public site header: skip link, the admin's announcement bar (Website →
     * Homepage → Announcement bar) and the sticky main navigation.
     *
     * Desktop: logo left · Tools (mega-menu of every feature page) /
     * Resources / Pricing in the centre · Talk To An Expert / Login / Get
     * Started on the right. Below lg: a slide-in drawer with accordions and
     * the account buttons pinned to the bottom. Every label, description and
     * link is edited in Website → Navigation menu (App\Support\Website\
     * NavigationMenu); the tools come from Website → Features. The <head> is
     * partials/seo/head.
     */
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
    $logo = \App\Models\SiteSetting::get('logo_rectangular_path');
    $announcement = trim((string) \App\Models\SiteSetting::get('header_announcement', ''));
    $announcementLabel = trim((string) \App\Models\SiteSetting::get('header_announcement_link_label', ''));
    $announcementUrl = trim((string) \App\Models\SiteSetting::get('header_announcement_link_url', ''));
    $announcementUrl = $announcementUrl !== '' && (str_starts_with($announcementUrl, '#') || \App\Support\SafeHtml::isSafeUrl($announcementUrl)) ? $announcementUrl : null;

    $toolGroups = \App\Models\FeaturePage::menuGroups();
    $resources = NavigationMenu::resources();
    $isGuest = ! auth()->check();
    $cta = NavigationMenu::primaryCta();
    $expertUrl = NavigationMenu::expertUrl();
    $pricingActive = request()->routeIs('registration.pricing');
    $toolsActive = request()->routeIs('features.*');

    // Pack the feature groups into at most three balanced columns (two
    // short groups share a column) so the mega-menu stays compact.
    $total = array_sum(array_map('count', $toolGroups));
    $columnTarget = max(8, (int) ceil($total / 3));
    $toolColumns = [];
    foreach ($toolGroups as $groupKey => $pages) {
        $last = array_key_last($toolColumns);
        if ($last !== null && count($toolColumns) >= 1 && ($toolColumns[$last]['count'] + count($pages)) <= $columnTarget && count($toolColumns[$last]['groups']) < 2) {
            $toolColumns[$last]['groups'][$groupKey] = $pages;
            $toolColumns[$last]['count'] += count($pages);
        } else {
            $toolColumns[] = ['groups' => [$groupKey => $pages], 'count' => count($pages)];
        }
    }

    $toneFor = fn (string $group): string => [
        'research' => 'bg-brand-50 text-brand-600 ring-brand-500/15',
        'outreach' => 'bg-emerald-50 text-emerald-600 ring-emerald-500/15',
        'content' => 'bg-violet-50 text-violet-600 ring-violet-500/15',
        'grow' => 'bg-amber-50 text-amber-600 ring-amber-500/20',
    ][$group] ?? 'bg-brand-50 text-brand-600 ring-brand-500/15';
?>
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70] focus:rounded-full focus:bg-navy-900 focus:px-5 focus:py-2.5 focus:text-sm focus:font-semibold focus:text-white focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-gold-400">
    Skip to content
</a>

@if ($announcement !== '')
    <div class="relative overflow-hidden bg-gradient-to-r from-navy-950 via-brand-700 to-navy-950 text-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-3 gap-y-1 px-4 py-2 text-center text-xs sm:text-sm">
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

<header class="sticky top-0 z-50 border-b border-line/70 bg-white/90 backdrop-blur-md supports-[backdrop-filter]:bg-white/80" data-site-header>
    <div class="relative mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:grid lg:grid-cols-[1fr_auto_1fr] lg:px-8">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5 justify-self-start rounded-md font-display text-lg font-semibold text-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500" aria-label="{{ $siteName }} home">
            @if ($logo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="{{ $siteName }}" class="h-8 w-auto">
            @else
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-navy-900 to-brand-600 text-sm text-gold-300 shadow-sm" aria-hidden="true">{{ strtoupper(substr($siteName, 0, 2)) }}</span>
                <span>{{ $siteName }}</span>
            @endif
        </a>

        {{-- Desktop: centre menu --}}
        <nav class="hidden lg:block" aria-label="Main">
            <ul class="flex items-center gap-1 text-sm" role="list">
                @if ($toolGroups !== [])
                    <li data-nav-item>
                        <button type="button" class="group inline-flex items-center gap-1 rounded-full px-3.5 py-2 font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 aria-expanded:bg-surface-muted aria-expanded:text-navy-900 {{ $toolsActive ? 'text-brand-700' : 'text-ink-600 hover:bg-surface-muted hover:text-navy-900' }}"
                                aria-expanded="false" aria-controls="nav-panel-tools" data-nav-trigger>
                            {{ NavigationMenu::label('nav_tools_label') }}
                            <x-heroicon-m-chevron-down class="h-4 w-4 text-ink-400 transition group-aria-expanded:rotate-180" aria-hidden="true" />
                        </button>
                    </li>
                @endif
                @if ($resources !== [])
                    <li class="relative" data-nav-item>
                        <button type="button" class="group inline-flex items-center gap-1 rounded-full px-3.5 py-2 font-medium text-ink-600 transition hover:bg-surface-muted hover:text-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 aria-expanded:bg-surface-muted aria-expanded:text-navy-900"
                                aria-expanded="false" aria-controls="nav-panel-resources" data-nav-trigger>
                            {{ NavigationMenu::label('nav_resources_label') }}
                            <x-heroicon-m-chevron-down class="h-4 w-4 text-ink-400 transition group-aria-expanded:rotate-180" aria-hidden="true" />
                        </button>
                        <div id="nav-panel-resources" class="absolute left-1/2 top-full z-50 hidden w-[24rem] -translate-x-1/2 pt-3" data-nav-panel>
                            <div class="overflow-hidden rounded-2xl bg-white p-2 shadow-[0_24px_60px_-20px_rgba(11,30,61,.35)] ring-1 ring-navy-900/10">
                                <ul role="list">
                                    @foreach ($resources as $item)
                                        <li>
                                            <a href="{{ $item['url'] }}" @if ($item['external']) rel="noopener" @endif class="group flex items-start gap-3 rounded-xl p-3 transition hover:bg-surface-muted focus:bg-surface-muted focus:outline-none">
                                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-brand-500/15 transition group-hover:bg-brand-600 group-hover:text-white">
                                                    @include('marketing.home.partials.icon', ['icon' => $item['icon'], 'class' => 'h-5 w-5'])
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="flex items-center gap-1 text-sm font-semibold text-navy-900">
                                                        {{ $item['label'] }}
                                                        @if ($item['external'])
                                                            <x-heroicon-m-arrow-up-right class="h-3.5 w-3.5 text-ink-400" aria-hidden="true" />
                                                        @endif
                                                    </span>
                                                    @if ($item['description'] !== '')
                                                        <span class="mt-0.5 block text-xs leading-snug text-ink-600">{{ $item['description'] }}</span>
                                                    @endif
                                                </span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </li>
                @endif
                <li>
                    <a href="{{ route('registration.pricing') }}" @if ($pricingActive) aria-current="page" @endif
                       class="inline-flex rounded-full px-3.5 py-2 font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 {{ $pricingActive ? 'bg-brand-50 text-brand-700' : 'text-ink-600 hover:bg-surface-muted hover:text-navy-900' }}">{{ NavigationMenu::label('nav_pricing_label') }}</a>
                </li>
            </ul>
        </nav>

        {{-- Desktop: right menu; below lg only the main button + menu toggle --}}
        <div class="flex items-center gap-1.5 justify-self-end">
            <a href="{{ $expertUrl }}" class="hidden items-center gap-1.5 rounded-full px-3 py-2 text-sm font-medium text-ink-600 transition hover:bg-surface-muted hover:text-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 lg:inline-flex" data-nav-expert>
                <x-heroicon-o-chat-bubble-left-right class="h-4 w-4 text-brand-600" aria-hidden="true" />
                {{ NavigationMenu::label('nav_expert_label') }}
            </a>
            @if ($isGuest)
                <a href="{{ route('login') }}" class="hidden rounded-full px-3 py-2 text-sm font-medium text-navy-900 transition hover:bg-surface-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 lg:inline-flex" data-nav-login>{{ NavigationMenu::label('nav_login_label') }}</a>
            @endif
            <a href="{{ $cta['url'] }}" class="ml-1 hidden items-center gap-1.5 rounded-full bg-navy-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 sm:inline-flex" data-nav-cta>
                {{ $cta['label'] }}
                <x-heroicon-m-arrow-right class="h-4 w-4 text-gold-300" aria-hidden="true" />
            </a>
            <button type="button"
                    class="-mr-1 ml-1 inline-flex h-10 w-10 items-center justify-center rounded-full text-navy-900 hover:bg-surface-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 lg:hidden"
                    aria-controls="mobile-menu" aria-expanded="false" data-mobile-menu-toggle>
                <span class="sr-only">Open menu</span>
                <x-heroicon-o-bars-3 class="h-6 w-6" aria-hidden="true" />
            </button>
        </div>
    </div>

    {{-- Desktop: Tools mega-menu (full width under the bar) --}}
    @if ($toolGroups !== [])
        <div id="nav-panel-tools" class="absolute inset-x-0 top-full z-40 hidden px-4 pt-2 sm:px-6 lg:px-8" data-nav-panel data-nav-mega>
            <div class="mx-auto max-w-7xl overflow-hidden rounded-3xl bg-white shadow-[0_32px_80px_-24px_rgba(11,30,61,.4)] ring-1 ring-navy-900/10">
                <div class="grid grid-cols-12">
                    <div class="col-span-9 grid grid-cols-3 gap-x-4 p-6">
                        @foreach ($toolColumns as $column)
                            <div class="space-y-6">
                                @foreach ($column['groups'] as $groupKey => $pages)
                                    <div>
                                        <p class="px-2 font-mono text-[10px] uppercase tracking-[0.18em] text-ink-400">{{ NavigationMenu::label('nav_group_'.$groupKey.'_label') }}</p>
                                        <ul class="mt-2" role="list">
                                            @foreach ($pages as $tool)
                                                <li>
                                                    <a href="{{ $tool->url() }}" class="group flex items-start gap-3 rounded-xl px-2 py-2 transition hover:bg-surface-muted focus:bg-surface-muted focus:outline-none" @if (request()->is('features/'.$tool->slug)) aria-current="page" @endif>
                                                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ring-1 transition {{ $toneFor($groupKey) }}">
                                                            @include('marketing.home.partials.icon', ['icon' => $tool->icon, 'class' => 'h-[18px] w-[18px]'])
                                                        </span>
                                                        <span class="min-w-0">
                                                            <span class="block text-sm font-semibold leading-tight text-navy-900 group-hover:text-brand-700">{{ $tool->name }}</span>
                                                            @if ($tool->menu_description)
                                                                <span class="mt-0.5 block text-xs leading-snug text-ink-600">{{ $tool->menu_description }}</span>
                                                            @endif
                                                        </span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="relative col-span-3 isolate flex flex-col justify-between overflow-hidden bg-navy-950 p-6 text-white">
                        <div class="absolute inset-0 -z-10" aria-hidden="true" style="background: radial-gradient(80% 60% at 0% 0%, rgba(36,82,217,.55) 0%, transparent 70%), radial-gradient(70% 60% at 100% 100%, rgba(201,162,74,.35) 0%, transparent 70%);"></div>
                        <div>
                            <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-gold-300">{{ NavigationMenu::label('nav_tools_promo_eyebrow') }}</p>
                            <p class="mt-3 font-display text-lg font-semibold leading-snug">{{ NavigationMenu::label('nav_tools_promo_heading') }}</p>
                            <a href="{{ NavigationMenu::urlFor('how-it-works') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-white/10 px-4 py-2 text-sm font-semibold ring-1 ring-white/20 transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold-300">
                                <x-heroicon-m-play-circle class="h-4 w-4 text-gold-300" aria-hidden="true" />
                                {{ NavigationMenu::label('nav_tools_promo_label') }}
                            </a>
                        </div>
                        <a href="{{ route('features.index') }}" class="group mt-8 block rounded-2xl bg-white/10 p-4 ring-1 ring-white/15 transition hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold-300">
                            <span class="flex items-center justify-between text-sm font-semibold">
                                {{ NavigationMenu::label('nav_all_features_label') }}
                                <x-heroicon-m-arrow-right class="h-4 w-4 text-gold-300 transition group-hover:translate-x-0.5" aria-hidden="true" />
                            </span>
                            <span class="mt-1 block text-xs text-white/65">{{ NavigationMenu::label('nav_all_features_description') }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</header>

{{-- Tablet & mobile: slide-in drawer --}}
<div id="mobile-menu" class="fixed inset-0 z-[60] hidden lg:hidden" data-mobile-menu role="dialog" aria-modal="true" aria-label="Menu">
    <div class="absolute inset-0 bg-navy-950/50 opacity-0 backdrop-blur-sm transition-opacity duration-200" data-mobile-backdrop></div>
    <div class="absolute inset-y-0 right-0 flex w-full max-w-sm translate-x-full flex-col bg-white shadow-2xl transition-transform duration-300 ease-out motion-reduce:transition-none" data-mobile-drawer>
        <div class="flex h-16 shrink-0 items-center justify-between border-b border-line px-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-display text-lg font-semibold text-navy-900">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-navy-900 to-brand-600 text-sm text-gold-300" aria-hidden="true">{{ strtoupper(substr($siteName, 0, 2)) }}</span>
                {{ $siteName }}
            </a>
            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-navy-900 hover:bg-surface-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500" data-mobile-menu-close>
                <span class="sr-only">Close menu</span>
                <x-heroicon-o-x-mark class="h-6 w-6" aria-hidden="true" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto overscroll-contain px-3 py-4" aria-label="Mobile">
            <ul class="space-y-1" role="list">
                @if ($toolGroups !== [])
                    <li>
                        <button type="button" class="group flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-base font-semibold text-navy-900 hover:bg-surface-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500" aria-expanded="false" aria-controls="mobile-tools" data-accordion-trigger>
                            {{ NavigationMenu::label('nav_tools_label') }}
                            <x-heroicon-m-chevron-down class="h-5 w-5 text-ink-400 transition group-aria-expanded:rotate-180" aria-hidden="true" />
                        </button>
                        <div id="mobile-tools" class="hidden pb-2" data-accordion-panel>
                            @foreach ($toolGroups as $groupKey => $pages)
                                <p class="mt-3 px-3 font-mono text-[10px] uppercase tracking-[0.18em] text-ink-400">{{ NavigationMenu::label('nav_group_'.$groupKey.'_label') }}</p>
                                <ul class="mt-1 grid gap-0.5 sm:grid-cols-2" role="list">
                                    @foreach ($pages as $tool)
                                        <li>
                                            <a href="{{ $tool->url() }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-navy-900 hover:bg-surface-muted">
                                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg ring-1 {{ $toneFor($groupKey) }}">
                                                    @include('marketing.home.partials.icon', ['icon' => $tool->icon, 'class' => 'h-4 w-4'])
                                                </span>
                                                {{ $tool->name }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endforeach
                            <a href="{{ route('features.index') }}" class="mx-3 mt-3 flex items-center justify-between rounded-xl bg-surface-muted px-3 py-2.5 text-sm font-semibold text-brand-700">
                                {{ NavigationMenu::label('nav_all_features_label') }}
                                <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" />
                            </a>
                        </div>
                    </li>
                @endif
                @if ($resources !== [])
                    <li>
                        <button type="button" class="group flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-base font-semibold text-navy-900 hover:bg-surface-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500" aria-expanded="false" aria-controls="mobile-resources" data-accordion-trigger>
                            {{ NavigationMenu::label('nav_resources_label') }}
                            <x-heroicon-m-chevron-down class="h-5 w-5 text-ink-400 transition group-aria-expanded:rotate-180" aria-hidden="true" />
                        </button>
                        <ul id="mobile-resources" class="hidden pb-2" role="list" data-accordion-panel>
                            @foreach ($resources as $item)
                                <li>
                                    <a href="{{ $item['url'] }}" @if ($item['external']) rel="noopener" @endif class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-navy-900 hover:bg-surface-muted">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-brand-500/15">
                                            @include('marketing.home.partials.icon', ['icon' => $item['icon'], 'class' => 'h-4 w-4'])
                                        </span>
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endif
                <li>
                    <a href="{{ route('registration.pricing') }}" @if ($pricingActive) aria-current="page" @endif class="flex items-center justify-between rounded-xl px-3 py-3 text-base font-semibold text-navy-900 hover:bg-surface-muted">
                        {{ NavigationMenu::label('nav_pricing_label') }}
                        <x-heroicon-m-chevron-right class="h-5 w-5 text-ink-400" aria-hidden="true" />
                    </a>
                </li>
                <li>
                    <a href="{{ $expertUrl }}" class="flex items-center justify-between rounded-xl px-3 py-3 text-base font-semibold text-navy-900 hover:bg-surface-muted">
                        <span class="inline-flex items-center gap-2">
                            <x-heroicon-o-chat-bubble-left-right class="h-5 w-5 text-brand-600" aria-hidden="true" />
                            {{ NavigationMenu::label('nav_expert_label') }}
                        </span>
                        <x-heroicon-m-chevron-right class="h-5 w-5 text-ink-400" aria-hidden="true" />
                    </a>
                </li>
            </ul>
        </nav>

        <div class="shrink-0 border-t border-line bg-surface-muted/60 p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
            <div class="grid {{ $isGuest ? 'grid-cols-2' : 'grid-cols-1' }} gap-2">
                @if ($isGuest)
                    <a href="{{ route('login') }}" class="rounded-full bg-white px-4 py-3 text-center text-sm font-semibold text-navy-900 ring-1 ring-line hover:bg-surface-muted">{{ NavigationMenu::label('nav_login_label') }}</a>
                @endif
                <a href="{{ $cta['url'] }}" class="inline-flex items-center justify-center gap-1.5 rounded-full bg-navy-900 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-navy-800">
                    {{ $cta['label'] }}
                    <x-heroicon-m-arrow-right class="h-4 w-4 text-gold-300" aria-hidden="true" />
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var header = document.querySelector('[data-site-header]');
        if (! header) { return; }

        /* Desktop dropdowns: click or hover (with intent delay) to open; Esc,
           a click outside or tabbing away closes; arrow keys move between
           links. */
        var triggers = Array.prototype.slice.call(header.querySelectorAll('[data-nav-trigger]'));
        var canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        var openTimer = null;
        var closeTimer = null;
        var panelFor = function (trigger) { return document.getElementById(trigger.getAttribute('aria-controls')); };
        var links = function (panel) { return Array.prototype.slice.call(panel.querySelectorAll('a')); };

        var setOpen = function (trigger, open) {
            var panel = panelFor(trigger);
            if (! panel) { return; }
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            panel.classList.toggle('hidden', ! open);
        };
        var closeAll = function (except) {
            triggers.forEach(function (t) { if (t !== except) { setOpen(t, false); } });
        };

        triggers.forEach(function (trigger) {
            var panel = panelFor(trigger);
            var item = trigger.closest('[data-nav-item]');

            trigger.addEventListener('click', function () {
                var open = trigger.getAttribute('aria-expanded') !== 'true';
                closeAll(trigger);
                setOpen(trigger, open);
            });
            trigger.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    closeAll(trigger);
                    setOpen(trigger, true);
                    var first = links(panel)[0];
                    if (first) { first.focus(); }
                }
            });
            panel.addEventListener('keydown', function (event) {
                var items = links(panel);
                var index = items.indexOf(document.activeElement);
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    var next = items[(index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length];
                    if (next) { next.focus(); }
                }
            });

            if (canHover) {
                [item, panel].forEach(function (zone) {
                    if (! zone) { return; }
                    zone.addEventListener('mouseenter', function () {
                        clearTimeout(closeTimer);
                        clearTimeout(openTimer);
                        openTimer = setTimeout(function () { closeAll(trigger); setOpen(trigger, true); }, 90);
                    });
                    zone.addEventListener('mouseleave', function () {
                        clearTimeout(openTimer);
                        closeTimer = setTimeout(function () { setOpen(trigger, false); }, 220);
                    });
                });
            }
        });

        document.addEventListener('click', function (event) {
            if (! event.target.closest('[data-nav-item]') && ! event.target.closest('[data-nav-panel]')) { closeAll(); }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') { return; }
            var open = triggers.filter(function (t) { return t.getAttribute('aria-expanded') === 'true'; })[0];
            if (open) { setOpen(open, false); open.focus(); }
        });
        header.addEventListener('focusout', function (event) {
            if (event.relatedTarget && ! header.contains(event.relatedTarget)) { closeAll(); }
        });

        /* Drawer (below lg). */
        var toggle = header.querySelector('[data-mobile-menu-toggle]');
        var menu = document.querySelector('[data-mobile-menu]');
        if (! toggle || ! menu) { return; }
        var drawer = menu.querySelector('[data-mobile-drawer]');
        var backdrop = menu.querySelector('[data-mobile-backdrop]');
        var setDrawer = function (open) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                menu.classList.remove('hidden');
                document.documentElement.style.overflow = 'hidden';
                requestAnimationFrame(function () {
                    drawer.classList.remove('translate-x-full');
                    backdrop.classList.remove('opacity-0');
                    var first = menu.querySelector('[data-mobile-menu-close]');
                    if (first) { first.focus(); }
                });
            } else {
                drawer.classList.add('translate-x-full');
                backdrop.classList.add('opacity-0');
                document.documentElement.style.overflow = '';
                setTimeout(function () { if (toggle.getAttribute('aria-expanded') !== 'true') { menu.classList.add('hidden'); } }, 250);
            }
        };
        toggle.addEventListener('click', function () { setDrawer(true); });
        menu.querySelector('[data-mobile-menu-close]').addEventListener('click', function () { setDrawer(false); toggle.focus(); });
        backdrop.addEventListener('click', function () { setDrawer(false); });
        menu.addEventListener('click', function (event) { if (event.target.closest('a')) { setDrawer(false); } });
        menu.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { setDrawer(false); toggle.focus(); return; }
            if (event.key !== 'Tab') { return; }
            var focusable = Array.prototype.slice.call(drawer.querySelectorAll('a, button')).filter(function (el) { return el.offsetParent !== null; });
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (! event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
        Array.prototype.forEach.call(menu.querySelectorAll('[data-accordion-trigger]'), function (button) {
            button.addEventListener('click', function () {
                var open = button.getAttribute('aria-expanded') !== 'true';
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                document.getElementById(button.getAttribute('aria-controls')).classList.toggle('hidden', ! open);
            });
        });
    })();
</script>
