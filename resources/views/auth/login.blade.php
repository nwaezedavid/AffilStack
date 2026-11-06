@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h2 class="font-display font-semibold text-xl text-navy-900 mb-1">Welcome back</h2>
    <p class="text-sm text-ink-600 mb-6">Sign in to your AffilStack dashboard.</p>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium text-ink-900 mb-1">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-ink-900 mb-1">Password</label>
            <input id="password" type="password" name="password" required
                   class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-ink-600">
                <input type="checkbox" name="remember" class="rounded border-line">
                Remember me
            </label>
            <a href="{{ route('password.request') }}" class="text-brand-600 hover:text-brand-700">Forgot password?</a>
        </div>
        <button type="submit" class="w-full rounded-md bg-navy-900 text-white text-sm font-medium py-2.5 hover:bg-navy-800 transition">
            Sign in
        </button>
    </form>

    @php
        $googleLogin = \App\Models\GoogleOauthSetting::current()->is_enabled;
        $appleLogin = app(\App\Services\Auth\AppleSignInService::class)->isEnabled();
    @endphp

    @if ($googleLogin || $appleLogin)
        <div class="flex items-center gap-3 my-6">
            <div class="flex-1 h-px bg-line"></div>
            <span class="text-xs text-ink-400 uppercase tracking-wide">or</span>
            <div class="flex-1 h-px bg-line"></div>
        </div>

        <div class="space-y-3">
            @if ($appleLogin)
                {{-- Apple's guidelines: black button, Apple logo, "Continue with Apple". --}}
                <a href="{{ route('apple.redirect') }}" class="w-full flex items-center justify-center gap-2 rounded-md bg-black text-white text-sm font-medium py-2.5 hover:bg-neutral-800 transition">
                    <svg class="h-4 w-4" viewBox="0 0 17 20" fill="currentColor" aria-hidden="true"><path d="M14.05 10.63c-.02-2.2 1.8-3.26 1.88-3.31-1.03-1.5-2.62-1.7-3.18-1.73-1.35-.14-2.64.8-3.33.8-.69 0-1.74-.78-2.86-.76-1.47.02-2.83.86-3.59 2.17-1.53 2.65-.39 6.58 1.1 8.73.73 1.05 1.6 2.24 2.73 2.19 1.1-.04 1.51-.71 2.84-.71 1.32 0 1.7.71 2.86.69 1.18-.02 1.93-1.07 2.65-2.13.84-1.22 1.18-2.4 1.2-2.46-.03-.01-2.28-.88-2.3-3.48ZM11.88 4.16c.6-.73 1.01-1.75.9-2.76-.87.04-1.92.58-2.54 1.31-.56.65-1.05 1.68-.92 2.67.97.08 1.96-.49 2.56-1.22Z"/></svg>
                    Continue with Apple
                </a>
            @endif
            @if ($googleLogin)
                <a href="{{ route('google.redirect') }}" class="w-full flex items-center justify-center gap-2 rounded-md border border-line text-sm font-medium py-2.5 hover:bg-surface transition">
                    Continue with Google
                </a>
            @endif
        </div>
    @endif

    {{-- Opened from the phone app: no links to plans (App Store / Google Play rules — see AppSignIn). --}}
    @if (\App\Support\Mobile\AppSignIn::active())
        <p class="mt-6 text-center text-sm text-ink-600">
            Sign in with your AffilStack account to use the app.
        </p>
    @else
        <p class="mt-6 text-center text-sm text-ink-600">
            New to AffilStack? <a href="{{ route('registration.pricing') }}" class="text-brand-600 hover:text-brand-700 font-medium">See plans &amp; pricing</a>
        </p>
    @endif
@endsection
