<?php
    $siteName = \App\Support\Brand::name();
    $user = auth()->user();
    $developer = $user?->developerProfile;
    $portalNav = [
        ['route' => 'developer.dashboard', 'match' => ['developer.dashboard', 'developer.addons.*'], 'label' => 'Add-ons', 'icon' => 'puzzle-piece'],
        ['route' => 'developer.earnings', 'match' => ['developer.earnings'], 'label' => 'Earnings', 'icon' => 'banknotes'],
        ['route' => 'developer.profile', 'match' => ['developer.profile'], 'label' => 'Profile', 'icon' => 'user-circle'],
    ];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Developers') · {{ $siteName }} Developers</title>
    <meta name="description" content="@yield('meta_description', 'Build connected apps, template packs and automation recipes for AffilStack, and earn from every subscription.')">
    @hasSection('noindex')<meta name="robots" content="noindex, nofollow">@endif
    @include('partials.seo.icons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-surface-muted text-ink-900 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-3 focus:py-2">Skip to content</a>
    <header class="bg-navy-950 text-white">
        <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3.5 sm:px-6 lg:px-8">
            <a href="{{ route('developer.home') }}" class="flex items-center gap-2.5 font-display text-lg font-semibold">
                <x-ui.brand-mark variant="dark" size="sm" :label="$siteName" />
                <span class="rounded-md bg-white/10 px-2 py-0.5 font-mono text-[11px] font-medium uppercase tracking-[0.14em] text-gold-300 ring-1 ring-white/15">Developers</span>
            </a>
            <nav class="ml-auto flex items-center gap-1 text-sm" aria-label="Developer portal">
                <a href="{{ route('developer.docs') }}" class="hidden rounded-lg px-3 py-2 text-white/80 hover:bg-white/10 hover:text-white sm:inline-flex {{ request()->routeIs('developer.docs') ? 'bg-white/10 text-white' : '' }}">Docs</a>
                <a href="{{ route('marketplace.index') }}" class="hidden rounded-lg px-3 py-2 text-white/80 hover:bg-white/10 hover:text-white sm:inline-flex">Marketplace</a>
                @if ($user)
                    <a href="{{ $developer ? route('developer.dashboard') : route('developer.join') }}" class="rounded-lg bg-gold-400 px-3.5 py-2 font-semibold text-navy-950 hover:bg-gold-300">{{ $developer ? 'Dashboard' : 'Become a developer' }}</a>
                    @if (\App\Support\SiteDomains::developerHost())
                        <form method="POST" action="{{ route('developer.logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg px-3 py-2 text-white/70 hover:bg-white/10 hover:text-white" title="Sign out">
                                <x-heroicon-o-arrow-right-on-rectangle class="h-5 w-5" aria-hidden="true" /><span class="sr-only">Sign out</span>
                            </button>
                        </form>
                    @endif
                @else
                    <a href="{{ route('developer.login') }}" class="rounded-lg bg-gold-400 px-3.5 py-2 font-semibold text-navy-950 hover:bg-gold-300">Sign in</a>
                @endif
            </nav>
        </div>
        @if ($developer && request()->routeIs('developer.dashboard', 'developer.addons.*', 'developer.earnings', 'developer.profile'))
            <div class="border-t border-white/10">
                <nav class="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-4 sm:px-6 lg:px-8" aria-label="Your developer account">
                    @foreach ($portalNav as $item)
                        @php($active = request()->routeIs(...$item['match']))
                        <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif class="inline-flex items-center gap-2 border-b-2 px-3 py-3 text-sm font-medium whitespace-nowrap {{ $active ? 'border-gold-400 text-white' : 'border-transparent text-white/65 hover:text-white' }}">
                            <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="h-4 w-4" aria-hidden="true" /> {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
        @endif
    </header>

    @yield('hero')

    <main id="main" class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        @if (session('success'))
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ \App\Support\Translation\Mark::flash(session('success')) }}</div>
        @endif
        @if (session('error'))
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ \App\Support\Translation\Mark::flash(session('error')) }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ \App\Support\Translation\Mark::text($error) }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="border-t border-line bg-surface">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-6 text-xs text-ink-400 sm:px-6 lg:px-8">
            <span>© {{ date('Y') }} {{ $siteName }}</span>
            <span class="flex gap-4">
                <a href="{{ \App\Support\SiteDomains::publicUrl('/') }}" class="hover:text-navy-900">{{ $siteName }}</a>
                <a href="{{ route('developer.docs', 'review') }}" class="hover:text-navy-900">Review guidelines</a>
                <a href="{{ \App\Support\SiteDomains::publicUrl('/contact') }}" class="hover:text-navy-900">Contact</a>
            </span>
        </div>
    </footer>
    @include('partials.consent.banner', ['context' => 'guest'])
    @stack('scripts')
</body>
</html>
