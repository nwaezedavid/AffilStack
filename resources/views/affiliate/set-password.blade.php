@extends('layouts.partner-auth')

@section('title', 'Set your password')

@section('content')
    <h1 class="font-display text-2xl font-semibold text-navy-900">Welcome, {{ $application->name }}!</h1>
    <p class="mt-1 mb-7 text-sm text-ink-600">You're approved as an AffilStack partner. Set a password to open your Partner Portal.</p>

    <form method="POST" action="{{ route('affiliate.set-password.store', $application) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-ink-900">Email</label>
            <input id="email" type="email" value="{{ $application->email }}" disabled class="w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600 bg-surface-muted text-ink-400">
        </div>
        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-ink-900">Password</label>
            <input id="password" type="password" name="password" required autofocus autocomplete="new-password" class="w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600">
        </div>
        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium text-ink-900">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600">
        </div>
        <button type="submit" class="w-full rounded-lg bg-emerald-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 transition">Set password and open my portal</button>
    </form>
@endsection
