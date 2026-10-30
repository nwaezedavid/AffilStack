<?php
    $siteName = \App\Support\Brand::name();
    $partnerPercent = \App\Support\ReferralProgram::formatPercent(\App\Support\ReferralProgram::partnerPercent());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Partner sign in') · {{ $siteName }} Partner Portal</title>
    @include('partials.seo.icons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface-muted text-ink-900 antialiased">
    <div class="min-h-screen grid lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
        <aside class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-gradient-to-br from-emerald-950 via-emerald-900 to-teal-800 px-12 py-10 text-white">
            <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-emerald-400/10 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-gold-400/10 blur-3xl" aria-hidden="true"></div>
            <a href="{{ route('affiliate.landing') }}" class="relative inline-flex items-center gap-3">
                <span class="inline-flex items-center gap-2.5 font-display text-xl font-semibold"><x-ui.brand-mark variant="dark" :label="$siteName" /></span>
                <span class="rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-mono uppercase tracking-[0.16em] text-emerald-100 ring-1 ring-white/15">Partners</span>
            </a>
            <div class="relative max-w-md">
                <p class="text-[11px] font-mono uppercase tracking-[0.2em] text-gold-300">Partner Portal</p>
                <p class="mt-3 font-display text-4xl font-semibold leading-tight text-balance">Earn {{ $partnerPercent }} on every subscription you refer.</p>
                <p class="mt-4 text-emerald-100/80">First payment and every renewal after it, tracked to you for {{ \App\Support\ReferralProgram::cookieDays() }} days. Paid to your Nigerian bank, PayPal or USDT wallet.</p>
                <dl class="mt-8 grid grid-cols-3 gap-3 text-sm">
                    <div class="rounded-xl bg-white/5 p-3 ring-1 ring-white/10"><dt class="text-emerald-100/60 text-xs">Live stats</dt><dd class="mt-1 font-semibold">Clicks to cash</dd></div>
                    <div class="rounded-xl bg-white/5 p-3 ring-1 ring-white/10"><dt class="text-emerald-100/60 text-xs">Ads</dt><dd class="mt-1 font-semibold">Auto-updating</dd></div>
                    <div class="rounded-xl bg-white/5 p-3 ring-1 ring-white/10"><dt class="text-emerald-100/60 text-xs">Security</dt><dd class="mt-1 font-semibold">2-step sign-in</dd></div>
                </dl>
            </div>
            <p class="relative text-xs text-emerald-100/50">&copy; {{ now()->year }} {{ $siteName }}</p>
        </aside>

        <main class="flex items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <a href="{{ route('affiliate.landing') }}" class="mb-8 inline-flex items-center gap-2 lg:hidden">
                    <span class="inline-flex items-center gap-2.5 font-display text-xl font-semibold text-navy-900"><x-ui.brand-mark :label="$siteName" /></span>
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-mono uppercase tracking-[0.16em] text-emerald-700">Partners</span>
                </a>

                @foreach (['success' => 'text-emerald-800 bg-emerald-50 border-emerald-200', 'status' => 'text-emerald-800 bg-emerald-50 border-emerald-200', 'error' => 'text-red-700 bg-red-50 border-red-200'] as $key => $classes)
                    @if (session($key))
                        <div class="mb-4 rounded-lg border px-3.5 py-2.5 text-sm {{ $classes }}" role="status">{{ session($key) }}</div>
                    @endif
                @endforeach
                @if ($errors->any())
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm text-red-700" role="alert">
                        <ul class="space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ \App\Support\Translation\Mark::text($error) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
            <div class="mt-6 flex justify-center">@include('partials.locale.switcher-form', ['tone' => 'light'])</div>
        </main>
    </div>
    @include('partials.consent.banner', ['context' => 'guest'])
</body>
</html>
