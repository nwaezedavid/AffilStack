@extends('layouts.partner-auth')

@section('title', 'Two-step sign in')

@section('content')
    <h1 class="font-display text-2xl font-semibold text-navy-900">Enter your code</h1>
    <p class="mt-1 mb-7 text-sm text-ink-600">Open your authenticator app and type the 6-digit code for {{ \App\Support\Brand::name() }}.</p>

    <form method="POST" action="{{ route('partner.two-factor.store') }}" class="space-y-4" id="code-form">
        @csrf
        <div>
            <label for="code" class="mb-1 block text-sm font-medium text-ink-900">Authentication code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="8" autofocus class="w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600 font-mono tracking-[0.3em]">
        </div>
        <button type="submit" class="w-full rounded-lg bg-emerald-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 transition">Verify and sign in</button>
    </form>

    <details class="mt-6 rounded-lg border border-line bg-white p-4">
        <summary class="cursor-pointer text-sm font-medium text-emerald-700">Lost your phone? Use a recovery code</summary>
        <form method="POST" action="{{ route('partner.two-factor.store') }}" class="mt-3 space-y-3">
            @csrf
            <label for="recovery_code" class="sr-only">Recovery code</label>
            <input id="recovery_code" name="recovery_code" autocomplete="off" placeholder="xxxxxxxxxx-xxxxxxxxxx" class="w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600 font-mono">
            <button type="submit" class="w-full rounded-lg border border-line bg-white px-4 py-2.5 text-sm font-semibold text-navy-900 hover:bg-surface-muted">Use recovery code</button>
        </form>
    </details>

    <p class="mt-6 text-center text-sm"><a href="{{ route('partner.login') }}" class="text-ink-600 hover:text-ink-900">Back to sign in</a></p>
@endsection
