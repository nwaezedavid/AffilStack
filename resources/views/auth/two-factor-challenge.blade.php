@extends('layouts.guest')

@section('title', 'Two-factor verification')

@section('content')
    <h2 class="font-display font-semibold text-xl text-navy-900 mb-1">Two-factor verification</h2>
    <p class="text-sm text-ink-600 mb-6">Enter the 6-digit code from your authenticator app.</p>

    <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="code" class="block text-sm font-medium text-ink-900 mb-1">Authentication code</label>
            <input id="code" type="text" inputmode="numeric" name="code" autofocus
                   class="w-full rounded-md border border-line px-3 py-2 text-sm tracking-widest focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>

        <details class="text-sm text-ink-600">
            <summary class="cursor-pointer text-brand-600 hover:text-brand-700 select-none">Lost access to your device? Use a recovery code</summary>
            <div class="mt-3">
                <label for="recovery_code" class="block text-sm font-medium text-ink-900 mb-1">Recovery code</label>
                <input id="recovery_code" type="text" name="recovery_code"
                       class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
        </details>

        <button type="submit" class="w-full rounded-md bg-navy-900 text-white text-sm font-medium py-2.5 hover:bg-navy-800 transition">
            Verify
        </button>
    </form>
@endsection
