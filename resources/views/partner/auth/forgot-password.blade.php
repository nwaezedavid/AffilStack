@extends('layouts.partner-auth')

@section('title', 'Reset your password')

@section('content')
    <h1 class="font-display text-2xl font-semibold text-navy-900">Reset your password</h1>
    <p class="mt-1 mb-7 text-sm text-ink-600">Enter the email on your partner account and we'll send you a reset link.</p>

    <form method="POST" action="{{ route('partner.password.email') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-ink-900">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600">
        </div>
        <button type="submit" class="w-full rounded-lg bg-emerald-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 transition">Send reset link</button>
    </form>

    <p class="mt-6 text-center text-sm"><a href="{{ route('partner.login') }}" class="text-ink-600 hover:text-ink-900">Back to sign in</a></p>
@endsection
