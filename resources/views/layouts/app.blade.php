<?php
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
    $logo = \App\Models\SiteSetting::get('logo_rectangular_path');
    $favicon = \App\Models\SiteSetting::get('logo_square_path');

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
    $sidebarBg = $isAffiliatePortal ? 'bg-emerald-950' : 'bg-navy-900';
    $sidebarBorder = $isAffiliatePortal ? 'border-emerald-800' : 'border-white/10';
    $sidebarHover = $isAffiliatePortal ? 'hover:bg-emerald-900/60' : 'hover:bg-white/5';
    $sidebarActive = $isAffiliatePortal ? 'bg-emerald-900 text-white' : 'bg-white/10 text-white';
    $sidebarMuted = $isAffiliatePortal ? 'text-emerald-100/70' : 'text-navy-100/70';
    $sidebarText = $isAffiliatePortal ? 'text-emerald-100/80' : 'text-navy-100/80';
    $portalTitle = $isAffiliatePortal ? 'Partner Portal' : $siteName;
    $gaId = \App\Models\SiteSetting::get('seo_ga_id');
    $metaPixelId = \App\Models\SiteSetting::get('seo_meta_pixel_id');
    $tiktokPixelId = \App\Models\SiteSetting::get('seo_tiktok_pixel_id');
    $gtmPublicId = \App\Models\GoogleSiteAnalyticsSetting::current()->credential('gtm_public_id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · {{ $isAffiliatePortal ? $siteName.' Partner Portal' : $siteName }}</title>
    @if ($favicon)
        <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($favicon) }}">
        <link rel="apple-touch-icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($favicon) }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.tracking-head')
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
        {{-- Sidebar --}}
        <aside class="w-64 shrink-0 {{ $sidebarBg }} text-white flex flex-col">
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

            <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
                @php
                    // A team seat (item 10) has no billing/CRM/earnings/
                    // referrals/support of its own — see
                    // config('agency.seat_allowed_routes'), enforced by
                    // RestrictAgencySeats. The nav mirrors that scope so a
                    // seat never sees a link that would just 403. An
                    // isolated-plan seat is scoped to its one offer; a
                    // shared-plan (Business tier) seat instead gets the
                    // whole team's offer list — see
                    // User::hasSharedTeamAccess().
                    $seatOfferItem = auth()->user()->isSeat() && auth()->user()->hasSharedTeamAccess()
                        ? ['name' => 'offers.index', 'match' => 'offers.*', 'label' => 'Offers', 'icon' => '🔎']
                        : ['name' => 'offers.show', 'params' => [auth()->user()->seat_offer_id], 'match' => 'offers.*', 'label' => 'My Product', 'icon' => '🔎'];

                    $items = auth()->user()->isSeat()
                        ? [
                            $seatOfferItem,
                            ['name' => 'calendar.index', 'match' => 'calendar.*', 'label' => 'Content Calendar', 'icon' => '📅'],
                            ['name' => 'swipe-files.index', 'match' => 'swipe-files.*', 'label' => 'Swipe Files', 'icon' => '🗂️'],
                            ['name' => 'social-connections.index', 'match' => 'social-connections.*', 'label' => 'Connected Accounts', 'icon' => '🔗'],
                            ['name' => 'api-access.index', 'match' => 'api-access.*', 'label' => 'API Access', 'icon' => '🔌'],
                        ]
                        : (auth()->user()->isAffiliateOnly()
                        // An affiliate-only account (see
                        // RestrictAffiliateOnlyAccounts) has nothing else on
                        // this account to link to — every other item here
                        // would just 403.
                        ? [
                            ['name' => 'referrals.index', 'match' => 'referrals.index', 'label' => 'Referrals', 'icon' => '🤝'],
                        ]
                        : [
                            ['name' => 'dashboard', 'match' => 'dashboard', 'label' => 'Overview', 'icon' => '🏠'],
                            ['name' => 'intelligence-centre.index', 'match' => 'intelligence-centre.*', 'label' => 'Intelligence Centre', 'icon' => '🧠'],
                            ['name' => 'offers.index', 'match' => 'offers.*', 'label' => 'Offer Research', 'icon' => '🔎'],
                            ['name' => 'crm.index', 'match' => 'crm.*', 'label' => 'CRM Contacts', 'icon' => '📇'],
                            ['name' => 'email-connections.index', 'match' => 'email-connections.*', 'label' => 'Email Sending', 'icon' => '📧'],
                            ['name' => 'social-connections.index', 'match' => 'social-connections.*', 'label' => 'Connected Accounts', 'icon' => '🔗'],
                            ['name' => 'leads.index', 'match' => 'leads.*', 'label' => 'Local Leads', 'icon' => '📍'],
                            ['name' => 'calendar.index', 'match' => 'calendar.*', 'label' => 'Content Calendar', 'icon' => '📅'],
                            ['name' => 'swipe-files.index', 'match' => 'swipe-files.*', 'label' => 'Swipe Files', 'icon' => '🗂️'],
                            ['name' => 'links.index', 'match' => 'links.*', 'label' => 'Links & Clicks', 'icon' => '🔗'],
                            ['name' => 'earnings.index', 'match' => 'earnings.*', 'label' => 'Earnings', 'icon' => '💰'],
                            ['name' => 'referrals.index', 'match' => 'referrals.index', 'label' => 'Referrals', 'icon' => '🤝'],
                            ['name' => 'team.index', 'match' => 'team.*', 'label' => 'Team', 'icon' => '👥'],
                            ['name' => 'extension.index', 'match' => 'extension.*', 'label' => 'Browser Extension', 'icon' => '🧩'],
                            ['name' => 'api-access.index', 'match' => 'api-access.*', 'label' => 'API Access', 'icon' => '🔌'],
                            ['name' => 'support.index', 'match' => 'support.*', 'label' => 'Support', 'icon' => '💬'],
                            ['name' => 'billing.index', 'match' => 'billing.*', 'label' => 'Billing & Plan', 'icon' => '💳'],
                            ['name' => 'credit-topups.index', 'match' => 'credit-topups.*', 'label' => 'Buy Credits', 'icon' => '⚡'],
                            ['name' => 'testimonial.edit', 'match' => 'testimonial.*', 'label' => 'Share a Review', 'icon' => '⭐'],
                        ]);
                @endphp
                @foreach ($items as $item)
                    <a href="{{ route($item['name'], $item['params'] ?? []) }}"
                       class="flex items-center gap-3 rounded-md px-3 py-2 transition {{ request()->routeIs($item['match']) ? $sidebarActive : $sidebarText.' '.$sidebarHover.' hover:text-white' }}">
                        <span aria-hidden="true">{{ $item['icon'] }}</span>
                        <span>{{ $item['label'] }}</span>
                    </a>
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
                @endunless
                <a href="{{ route('profile') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm {{ $sidebarText }} {{ $sidebarHover }} hover:text-white">
                    <span aria-hidden="true">⚙️</span> Profile & Security
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm {{ $sidebarText }} {{ $sidebarHover }} hover:text-white">
                        <span aria-hidden="true">↩</span> Sign out
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main --}}
        <div class="flex-1 min-w-0">
            <header class="bg-surface border-b border-line px-6 py-4 flex items-center justify-between">
                <div>
                    <h1 class="font-display font-semibold text-lg text-ink-900">@yield('title', 'Overview')</h1>
                    @if ($isAffiliatePortal)
                        <p class="text-xs text-emerald-700">Affiliate Partner Portal</p>
                    @endif
                </div>
                <div class="flex items-center gap-4">
                    <div id="notif-bell" class="relative">
                        <button type="button" id="notif-toggle" class="relative flex items-center justify-center h-9 w-9 rounded-md text-ink-600 hover:bg-surface-muted transition" aria-label="Notifications">
                            <span aria-hidden="true">🔔</span>
                            <span id="notif-badge" class="hidden absolute -top-1 -right-1 min-w-[16px] h-4 px-1 rounded-full bg-red-600 text-white text-[10px] leading-4 text-center font-mono"></span>
                        </button>
                        <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 max-h-96 overflow-y-auto bg-surface border border-line rounded-lg shadow-lg z-20">
                            <div class="flex items-center justify-between px-4 py-2.5 border-b border-line">
                                <span class="text-xs font-mono uppercase tracking-wide text-ink-400">Notifications</span>
                                <button type="button" id="notif-mark-all" class="text-xs text-brand-600 hover:text-brand-700">Mark all read</button>
                            </div>
                            <div id="notif-list" class="divide-y divide-line-soft">
                                <p class="px-4 py-6 text-center text-xs text-ink-400">No notifications yet.</p>
                            </div>
                        </div>
                    </div>
                    <div class="text-sm text-ink-600">{{ auth()->user()->name }}</div>
                </div>
            </header>

            <main class="p-6 max-w-5xl">
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
                list.innerHTML = items.map(function (n) {
                    const dot = n.read_at ? '' : '<span class="inline-block w-1.5 h-1.5 rounded-full bg-brand-600 mr-1.5 align-middle"></span>';
                    const tone = n.success ? 'text-ink-900' : 'text-red-700';
                    return '<a href="' + n.url + '" data-id="' + n.id + '" class="notif-item block px-4 py-3 hover:bg-surface-muted transition">'
                        + '<div class="text-xs ' + tone + '">' + dot + n.message + '</div>'
                        + '<div class="text-[11px] text-ink-400 mt-0.5">' + n.created_at + '</div>'
                        + '</a>';
                }).join('');
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

    @stack('scripts')
</body>
</html>
