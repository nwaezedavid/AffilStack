@extends('layouts.partner-auth')

@section('title', 'Choose a new password')

@section('content')
    <h1 class="font-display text-2xl font-semibold text-navy-900">Choose a new password</h1>
    <p class="mt-1 mb-7 text-sm text-ink-600">Use at least 8 characters. A passphrase is easiest to remember.</p>

    <form method="POST" action="{{ route('partner.password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-ink-900">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username" class="w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600">
        </div>
        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-ink-900">New password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" class="w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600">
        </div>
        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium text-ink-900">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600">
        </div>
        <button type="submit" class="w-full rounded-lg bg-emerald-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 transition">Save new password</button>
    </form>
@endsection
