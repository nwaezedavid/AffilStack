@extends('layouts.partner-auth')

@section('title', 'Partner sign in')

@section('content')
    <h1 class="font-display text-2xl font-semibold text-navy-900">Partner sign in</h1>
    <p class="mt-1 mb-7 text-sm text-ink-600">Your links, banners, stats and payouts.</p>

    <form method="POST" action="{{ route('partner.login.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-ink-900">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600">
        </div>
        <div>
            <div class="mb-1 flex items-center justify-between">
                <label for="password" class="block text-sm font-medium text-ink-900">Password</label>
                <a href="{{ route('partner.password.request') }}" class="text-sm text-emerald-700 hover:text-emerald-800">Forgot password?</a>
            </div>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600">
        </div>
        <label class="flex items-center gap-2 text-sm text-ink-600">
            <input type="checkbox" name="remember" value="1" class="rounded border-line text-emerald-700 focus:ring-emerald-600">
            Keep me signed in on this device
        </label>
        <button type="submit" class="w-full rounded-lg bg-emerald-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 transition">Sign in</button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-600">
        Not a partner yet? <a href="{{ route('affiliate.landing') }}#apply" class="font-medium text-emerald-700 hover:text-emerald-800">Apply to join</a>
    </p>
    <p class="mt-2 text-center text-xs text-ink-400">
        AffilStack customer? Your referral link is in your <a href="{{ route('referrals.index') }}" class="underline hover:text-ink-600">dashboard</a>.
    </p>
@endsection
