<?php
    $partner = auth()->user();
    $siteName = \App\Support\Brand::name();
    $whiteLogo = \App\Support\Brand::whiteLogoUrl();
    $whiteIcon = \App\Support\Brand::whiteIconUrl();
    $unreadNews = \App\Models\PartnerAnnouncement::query()->published()
        ->when($partner->partner_news_seen_at, fn ($q) => $q->where('published_at', '>', $partner->partner_news_seen_at))
        ->count();
    $nav = [
        ['route' => 'partner.dashboard', 'label' => 'Overview', 'icon' => 'chart-bar-square'],
        ['route' => 'partner.links', 'label' => 'Links & campaigns', 'icon' => 'link'],
        ['route' => 'partner.banners', 'label' => 'Banners & ads', 'icon' => 'photo'],
        ['route' => 'partner.promote', 'label' => 'Emails & posts', 'icon' => 'megaphone'],
        ['route' => 'partner.payouts', 'label' => 'Earnings & payouts', 'icon' => 'banknotes'],
        ['route' => 'partner.news', 'label' => 'News', 'icon' => 'newspaper', 'badge' => $unreadNews],
    ];
    if (\App\Support\ReferralProgram::leaderboardEnabled()) {
        $nav[] = ['route' => 'partner.leaderboard', 'label' => 'Leaderboard', 'icon' => 'trophy'];
    }
    $nav[] = ['route' => 'partner.guidelines', 'label' => 'Guidelines', 'icon' => 'shield-check'];
    $partnerPercent = \App\Support\ReferralProgram::formatPercent(\App\Support\ReferralProgram::partnerPercent());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Overview') · {{ $siteName }} Partner Portal</title>
    @include('partials.seo.icons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-surface-muted text-ink-900 antialiased">
    <div class="min-h-screen flex">
        <input type="checkbox" id="partner-nav" class="peer sr-only">
        <label for="partner-nav" class="hidden peer-checked:block md:hidden fixed inset-0 bg-black/40 z-30" aria-hidden="true"></label>

        <aside class="w-64 shrink-0 bg-gradient-to-b from-emerald-950 to-emerald-900 text-white flex flex-col fixed inset-y-0 left-0 z-40 -translate-x-full transition-transform duration-200 ease-out peer-checked:translate-x-0 md:sticky md:top-0 md:h-screen md:translate-x-0">
            <div class="px-5 py-5 border-b border-emerald-800">
                <a href="{{ route('partner.dashboard') }}" class="flex items-center gap-2.5 font-display font-semibold text-lg">
                    @if ($whiteLogo)
                        <img src="{{ $whiteLogo }}" alt="{{ $siteName }}" class="h-7 w-auto">
                    @elseif ($whiteIcon)
                        <img src="{{ $whiteIcon }}" alt="" class="h-8 w-8 object-contain" aria-hidden="true"> {{ $siteName }}
                    @else
                        {{ $siteName }}
                    @endif
                </a>
                <p class="mt-1.5 text-[11px] font-mono uppercase tracking-[0.18em] text-emerald-100/60">Partner Portal</p>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5 text-sm" aria-label="Partner Portal">
                @foreach ($nav as $item)
                    @php($active = request()->routeIs($item['route']))
                    <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                       class="group relative flex items-center gap-3 rounded-lg px-3 py-2 transition {{ $active ? 'bg-emerald-800/70 text-white' : 'text-emerald-100/80 hover:bg-emerald-900/60 hover:text-white' }}">
                        @if ($active)
                            <span class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r-full bg-gold-400" aria-hidden="true"></span>
                        @endif
                        <x-dynamic-component :component="'heroicon-o-'.$item['icon']" @class(['h-5 w-5 shrink-0', 'text-gold-300' => $active, 'opacity-80 group-hover:opacity-100' => ! $active]) />
                        <span class="flex-1">{{ $item['label'] }}</span>
                        @if (($item['badge'] ?? 0) > 0)
                            <span class="min-w-5 rounded-full bg-gold-400 px-1.5 text-center text-[11px] font-semibold leading-5 text-emerald-950">{{ $item['badge'] > 9 ? '9+' : $item['badge'] }}<span class="sr-only"> unread</span></span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="mx-3 mb-3 rounded-lg bg-emerald-900/60 border border-emerald-800 px-3 py-2.5 text-xs">
                <div class="flex items-center justify-between text-emerald-100/70">
                    <span>Your commission</span>
                    <span class="font-mono text-gold-300 font-semibold">{{ $partnerPercent }}</span>
                </div>
                <p class="mt-1 text-[11px] text-emerald-100/50">On every payment, renewals included.</p>
            </div>

            <div class="px-3 py-4 border-t border-emerald-800 space-y-1">
                <div class="px-3 pb-2">@include('partials.locale.switcher-form', ['tone' => 'dark'])</div>
                <a href="{{ route('partner.account') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm {{ request()->routeIs('partner.account') ? 'bg-emerald-800/70 text-white' : 'text-emerald-100/80 hover:bg-emerald-900/60 hover:text-white' }}">
                    <x-heroicon-o-user-circle class="h-5 w-5 shrink-0 opacity-80" /> Account & security
                </a>
                <form method="POST" action="{{ route('partner.logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-emerald-100/80 hover:bg-emerald-900/60 hover:text-white">
                        <x-heroicon-o-arrow-left-start-on-rectangle class="h-5 w-5 shrink-0 opacity-80" /> Sign out
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex-1 min-w-0">
            <header class="sticky top-0 z-20 bg-surface/90 backdrop-blur border-b border-line px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <label for="partner-nav" class="md:hidden shrink-0 -ml-1 p-2 rounded-md text-ink-600 hover:bg-surface-muted cursor-pointer" aria-label="Open menu">
                        <x-heroicon-o-bars-3 class="h-6 w-6" />
                    </label>
                    <div class="min-w-0">
                        <h1 class="font-display font-semibold text-lg text-ink-900 truncate">@yield('title', 'Overview')</h1>
                        <p class="text-xs text-emerald-700">Partner Portal</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span class="hidden sm:block"><x-ui.copy-button :text="$partner->referral_link" label="Copy my link" size="sm" /></span>
                    <a href="{{ route('partner.account') }}" class="flex items-center gap-2 rounded-full py-1 pl-1 pr-3 hover:bg-surface-muted transition">
                        <x-ui.avatar :user="$partner" class="h-8 w-8" />
                        <span class="hidden sm:block text-sm text-ink-900 truncate max-w-[10rem]">{{ $partner->name }}</span>
                    </a>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8 max-w-6xl">
                @if (session('success'))
                    <div class="mb-5 text-sm text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2.5 whitespace-pre-line" role="status">{{ \App\Support\Translation\Mark::flash(session('success')) }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-5 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-2.5" role="alert">{{ \App\Support\Translation\Mark::flash(session('error')) }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-5 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-2.5" role="alert">
                        <ul class="space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ \App\Support\Translation\Mark::text($error) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
    @include('partials.consent.banner', ['context' => 'dashboard'])
</body>
</html>
