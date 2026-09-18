@extends('layouts.guest')

@section('title', 'Set your password')

@section('content')
    <h1 class="font-display font-semibold text-xl text-navy-900 mb-1">Welcome, {{ $application->name }}!</h1>
    <p class="text-sm text-ink-600 mb-6">Your affiliate application is approved. Set a password to access your dashboard.</p>

    <form method="POST" action="{{ route('affiliate.set-password.store', $application) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="block text-sm font-medium text-ink-900 mb-1">Email</label>
            <input id="email" type="email" value="{{ $application->email }}" disabled
                class="w-full rounded-md border border-line px-3 py-2 text-sm bg-surface-muted text-ink-400">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-ink-900 mb-1">Password</label>
            <input id="password" type="password" name="password" required
                class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-ink-900 mb-1">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required
                class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>

        <button type="submit" class="w-full rounded-md bg-navy-900 text-white px-4 py-2.5 text-sm font-medium hover:bg-navy-800 transition">
            Set password & continue
        </button>
    </form>
@endsection
