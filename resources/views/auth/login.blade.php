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

    <p class="mt-6 text-center text-sm text-ink-600">
        New to AffilStack? <a href="{{ route('registration.pricing') }}" class="text-brand-600 hover:text-brand-700 font-medium">See plans &amp; pricing</a>
    </p>
@endsection
