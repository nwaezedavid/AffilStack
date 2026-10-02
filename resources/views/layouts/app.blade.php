<?php
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
    $logo = \App\Models\SiteSetting::get('logo_rectangular_path');

    // "I believe they will need a separate dashboard, different from the
    // users who are actual paid members of the platform" — an
    // affiliate-only account (User::isAffiliateOnly()) shares this same
    // shell and every one of its routes (they resolve correctly on the
    // affiliate subdomain too, since none of them carry a Route::domain()
    // constraint — Laravel builds route() URLs from the current request's
    // host), but renders with a distinctly-branded "Partner Portal" theme
    // instead of the customer navy sidebar, so the two account types never
    // look interchangeable even though they're the same User model and the
    // same session.
    $isAffiliatePortal = auth()->user()->isAffiliateOnly();
    $sidebarBg = $isAffiliatePortal ? 'bg-gradient-to-b from-emerald-950 to-emerald-900' : 'bg-gradient-to-b from-navy-950 via-navy-900 to-navy-800';
    $sidebarBorder = $isAffiliatePortal ? 'border-emerald-800' : 'border-white/10';
    $sidebarHover = $isAffiliatePortal ? 'hover:bg-emerald-900/60' : 'hover:bg-white/5';
    $sidebarActive = $isAffiliatePortal ? 'bg-emerald-900 text-white' : 'bg-white/10 text-white';
    $sidebarMuted = $isAffiliatePortal ? 'text-emerald-100/70' : 'text-navy-100/70';
    $sidebarText = $isAffiliatePortal ? 'text-emerald-100/80' : 'text-navy-100/80';
    $portalTitle = $isAffiliatePortal ? 'Partner Portal' : $siteName;
    $gaId = \App\Models\SiteSetting::get('seo_ga_id');
    $metaPixelId = \App\Models\SiteSetting::get('seo_meta_pixel_id');
    $tiktokPixelId = \App\Models\SiteSetting::get('seo_tiktok_pixel_id');
    $gtmPublicId = \App\Models\GoogleSiteAnalyticsSetting::cachedGtmPublicId();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · {{ $isAffiliatePortal ? $siteName.' Partner Portal' : $siteName }}</title>
    @include('partials.seo.icons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.tracking-head')
    @include('partials.analytics-beacon')
    @stack('head')
</head>
<body class="bg-surface-muted text-ink-900 antialiased">
    @include('partials.tracking-body')
    @if ($isAffiliatePortal)
        <div class="bg-emerald-950 text-emerald-100 text-xs text-center py-1.5 px-4">
            🤝 {{ $siteName }} Affiliate Partner Portal — this account earns commission, it doesn't have platform access.
        </div>
    @endif
    <div class="min-h-screen flex">
        {{-- Mobile sidebar toggle — pure-CSS checkbox/peer drawer (no Alpine
             on this layout). `sr-only` (not `hidden`) keeps the checkbox
             itself keyboard-focusable and screen-reader reachable. --}}
        <input type="checkbox" id="sidebar-toggle" class="peer sr-only">

        {{-- Backdrop: mobile only, shown while the drawer is open --}}
        <label for="sidebar-toggle" class="hidden peer-checked:block md:hidden fixed inset-0 bg-black/40 z-30" aria-hidden="true"></label>

        {{-- Sidebar: an off-canvas drawer below md, a normal in-flow column at md+ --}}
        <aside class="w-64 shrink-0 {{ $sidebarBg }} text-white flex flex-col
                      fixed inset-y-0 left-0 z-40 -translate-x-full transition-transform duration-200 ease-out
                      peer-checked:translate-x-0
                      md:sticky md:top-0 md:h-screen md:translate-x-0">
            <div class="px-5 py-5 border-b {{ $sidebarBorder }}">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-display font-semibold text-lg">
                    @if ($logo && ! $isAffiliatePortal)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="{{ $siteName }}" class="h-8 w-auto">
                    @else
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-white/10 text-gold-400 text-sm">{{ $isAffiliatePortal ? '🤝' : \Illuminate\Support\Str::of($siteName)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
                        {{ $portalTitle }}
                    @endif
                </a>
                @if ($isAffiliatePortal)
                    <p class="text-[11px] text-emerald-100/60 mt-1">Powered by {{ $siteName }}</p>
                @endif
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5 text-sm" aria-label="Dashboard">
                @foreach (\App\Support\DashboardNavigation::groupsFor(auth()->user()) as $group)
                    <div>
                        <p class="px-3 mb-1.5 text-[10px] font-mono uppercase tracking-[0.18em] {{ $sidebarMuted }}">{{ $group['label'] }}</p>
                        <div class="space-y-0.5">
                            @foreach ($group['items'] as $item)
                                @php($active = request()->routeIs($item['match']))
                                <a href="{{ route($item['name'], $item['params'] ?? []) }}"
                                   @if ($active) aria-current="page" @endif
                                   class="group relative flex items-center gap-3 rounded-lg px-3 py-2 transition {{ $active ? $sidebarActive : $sidebarText.' '.$sidebarHover.' hover:text-white' }}">
                                    @if ($active)
                                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r-full bg-gold-400" aria-hidden="true"></span>
                                    @endif
                                    <x-dynamic-component :component="'heroicon-o-'.$item['icon']" @class(['h-5 w-5 shrink-0', 'text-gold-300' => $active, 'opacity-80 group-hover:opacity-100' => ! $active]) />
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            @if ($isAffiliatePortal)
                <div class="mx-3 mb-4 rounded-md bg-emerald-900/50 border border-emerald-800 px-3 py-2.5 text-xs">
                    <div class="flex items-center justify-between {{ $sidebarMuted }}">
                        <span>Commission rate</span>
                        <span class="font-mono text-gold-400 font-semibold">{{ number_format(config('referrals.commission_rate') * 100) }}%</span>
                    </div>
                </div>
            @endif

            <div class="px-3 py-4 border-t {{ $sidebarBorder }} space-y-3">
                @unless ($isAffiliatePortal)
                    <div class="rounded-md bg-white/5 px-3 py-2.5 text-xs">
                        <div class="flex items-center justify-between text-navy-100/70">
                            <span>Credits{{ auth()->user()->isSeat() ? ' (team)' : '' }}</span>
                            <span class="font-mono text-gold-400 font-semibold">{{ number_format(auth()->user()->billableUser()->credits_balance) }}</span>
                        </div>
                    </div>
                    {{-- Mobile apps (agent 02): "Get the mobile app" card, hidden until a store link is set. --}}
                    @include('partials.dashboard.mobile-app-card')
                @endunless
                <a href="{{ route('profile') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm {{ $sidebarText }} {{ $sidebarHover }} hover:text-white">
                    <x-heroicon-o-user-circle class="h-5 w-5 shrink-0 opacity-80" /> Profile & Security
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm {{ $sidebarText }} {{ $sidebarHover }} hover:text-white">
                        <x-heroicon-o-arrow-left-start-on-rectangle class="h-5 w-5 shrink-0 opacity-80" /> Sign out
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main --}}
        <div class="flex-1 min-w-0">
            <header class="sticky top-0 z-20 bg-surface/90 backdrop-blur border-b border-line px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <label for="sidebar-toggle"
                           class="md:hidden shrink-0 -ml-1 p-2 rounded-md text-ink-600 hover:bg-surface-muted cursor-pointer peer-focus-visible:ring-2 peer-focus-visible:ring-navy-900"
                           aria-label="Open menu">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </label>
                    <div class="min-w-0">
                        <h1 class="font-display font-semibold text-lg text-ink-900 truncate">@yield('title', 'Overview')</h1>
                        @if ($isAffiliatePortal)
                            <p class="text-xs text-emerald-700">Affiliate Partner Portal</p>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:gap-4 shrink-0">
                    {{-- Agent 10: replay the welcome tour (partials/onboarding-tour). --}}
                    @unless ($isAffiliatePortal)
                        <a href="{{ route('onboarding.restart') }}" data-tour-restart class="hidden sm:inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium text-ink-600 ring-1 ring-line hover:bg-surface-muted hover:text-ink-900 transition">
                            <x-heroicon-o-academic-cap class="h-4 w-4" /> Take the tour
                        </a>
                    @endunless
                    <div id="notif-bell" class="relative">
                        <button type="button" id="notif-toggle" class="relative flex items-center justify-center h-9 w-9 rounded-md text-ink-600 hover:bg-surface-muted transition" aria-label="Notifications">
                            <x-heroicon-o-bell class="h-5 w-5" />
                            <span id="notif-badge" class="hidden absolute -top-1 -right-1 min-w-[16px] h-4 px-1 rounded-full bg-red-600 text-white text-[10px] leading-4 text-center font-mono"></span>
                        </button>
                        <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] max-h-96 overflow-y-auto bg-surface border border-line rounded-lg shadow-lg z-20">
                            <div class="flex items-center justify-between px-4 py-2.5 border-b border-line">
                                <span class="text-xs font-mono uppercase tracking-wide text-ink-400">Notifications</span>
                                <button type="button" id="notif-mark-all" class="text-xs text-brand-600 hover:text-brand-700">Mark all read</button>
                            </div>
                            <div id="notif-list" class="divide-y divide-line-soft">
                                <p class="px-4 py-6 text-center text-xs text-ink-400">No notifications yet.</p>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('profile') }}" class="hidden sm:flex items-center gap-2 rounded-full py-1 pl-1 pr-3 hover:bg-surface-muted transition">
                        <x-ui.avatar :user="auth()->user()" class="h-8 w-8" />
                        <span class="text-sm text-ink-900 truncate max-w-[10rem]">{{ auth()->user()->name }}</span>
                    </a>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8 max-w-6xl">
                @if (session('success'))
                    <div class="mb-5 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md px-4 py-2.5 whitespace-pre-line">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="mb-5 text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-4 py-2.5">
                        {{ session('error') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mb-5 text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-4 py-2.5">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (function () {
            const toggle = document.getElementById('notif-toggle');
            const dropdown = document.getElementById('notif-dropdown');
            const badge = document.getElementById('notif-badge');
            const list = document.getElementById('notif-list');
            const markAllBtn = document.getElementById('notif-mark-all');
            const pollUrl = @json(route('notifications.poll'));
            const markAllUrl = @json(route('notifications.read-all'));
            const csrf = @json(csrf_token());

            function render(items) {
                if (!items.length) {
                    list.innerHTML = '<p class="px-4 py-6 text-center text-xs text-ink-400">No notifications yet.</p>';
                    return;
                }
                // Built with DOM nodes and textContent, never innerHTML: a
                // notification's message embeds user-chosen text (an offer's
                // product name — which a team seat can set on the owner's
                // account), so string-built HTML here was a stored XSS.
                list.replaceChildren();
                items.forEach(function (n) {
                    const link = document.createElement('a');
                    let url = '#';
                    try {
                        const parsed = new URL(String(n.url), window.location.origin);
                        if (parsed.origin === window.location.origin) {
                            url = parsed.href;
                        }
                    } catch (e) {}
                    link.href = url;
                    link.dataset.id = n.id;
                    link.className = 'notif-item block px-4 py-3 hover:bg-surface-muted transition';

                    const message = document.createElement('div');
                    message.className = 'text-xs ' + (n.success ? 'text-ink-900' : 'text-red-700');
                    if (!n.read_at) {
                        const dot = document.createElement('span');
                        dot.className = 'inline-block w-1.5 h-1.5 rounded-full bg-brand-600 mr-1.5 align-middle';
                        message.appendChild(dot);
                    }
                    message.appendChild(document.createTextNode(String(n.message ?? '')));

                    const when = document.createElement('div');
                    when.className = 'text-[11px] text-ink-400 mt-0.5';
                    when.textContent = String(n.created_at ?? '');

                    link.append(message, when);
                    list.appendChild(link);
                });
            }

            function poll() {
                fetch(pollUrl, { headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (!data) {
                            return;
                        }
                        if (data.unread_count > 0) {
                            badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                        render(data.items);
                    })
                    .catch(function () {});
            }

            toggle.addEventListener('click', function () {
                dropdown.classList.toggle('hidden');
            });

            document.addEventListener('click', function (e) {
                if (!document.getElementById('notif-bell').contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });

            markAllBtn.addEventListener('click', function () {
                fetch(markAllUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                }).then(poll);
            });

            poll();
            setInterval(poll, 20000);
        })();
    </script>

    {{-- Agent 10: the dashboard welcome tour. --}}
    @include('partials.onboarding-tour')

    @stack('scripts')
</body>
</html>
