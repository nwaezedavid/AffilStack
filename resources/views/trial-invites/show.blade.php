@extends('layouts.guest')

@section('title', 'Your free trial')

@section('content')
    @php
        $plan = $invite->plan;
        $currency = strtoupper($plan->currency ?: 'USD');
        $price = \App\Models\CustomerWallet::format($invite->priceCents(), $currency);
        $per = $invite->billing_cycle === 'yearly' ? 'year' : 'month';
    @endphp

    <h2 class="font-display font-semibold text-xl text-navy-900 mb-1">Try AffilStack free for 7 days</h2>
    <p class="text-sm text-ink-600 mb-6">
        {{ $plan->name }} plan · free for 7 days, then {{ $price }} per {{ $per }}. Cancel any time before the trial ends and you won't be charged.
    </p>

    @if (session('error'))
        <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    @if ($blockingReason)
        <div class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">{{ $blockingReason }}</div>
    @elseif ($user)
        <p class="text-sm text-ink-900 mb-4">Signed in as <strong>{{ $user->email }}</strong>. Save a card to start your trial.</p>

        @if (empty($gateways))
            <p class="text-sm text-ink-600">Card payments are temporarily unavailable — please try again shortly.</p>
        @else
            <form method="POST" action="{{ route('trial-invites.start', $invite) }}" class="space-y-4">
                @csrf
                <x-checkout.gateway-select :gateways="$gateways" />
                <button type="submit" class="w-full rounded-md bg-navy-900 text-white text-sm font-medium py-2.5 hover:bg-navy-800 transition">
                    Save card &amp; start my free trial
                </button>
            </form>
            <p class="text-xs text-ink-400 mt-3">
                To save your card we make a {{ \App\Models\CustomerWallet::format($cardCheckMinor, $currency) }} card check. It's added to your AffilStack wallet and goes toward your first payment.
            </p>
        @endif
    @elseif ($accountExists)
        <p class="text-sm text-ink-900 mb-4">You already have an AffilStack account with <strong>{{ $invite->email }}</strong>. Sign in to start your trial.</p>
        <a href="{{ route('login') }}" class="block w-full text-center rounded-md bg-navy-900 text-white text-sm font-medium py-2.5 hover:bg-navy-800 transition">Sign in</a>
    @else
        <form method="POST" action="{{ route('trial-invites.register', $invite) }}" class="space-y-4">
            @csrf
            <div>
                <label for="name" class="block text-sm font-medium text-ink-900 mb-1">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $invite->name) }}" required autofocus
                       class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink-900 mb-1">Email</label>
                <p class="w-full rounded-md border border-line bg-surface-muted px-3 py-2 text-sm text-ink-600">{{ $invite->email }}</p>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-ink-900 mb-1">Password</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                       class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-ink-900 mb-1">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                       class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <label class="flex items-start gap-2 text-sm text-ink-600">
                <input type="checkbox" name="accepts_refund_policy" value="1" class="mt-0.5 rounded border-line" required>
                <span>I accept the <a href="{{ route('refund-policy') }}" target="_blank" class="underline hover:text-ink-900">Refund &amp; Cancellation Policy</a>.</span>
            </label>
            @error('accepts_refund_policy')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <button type="submit" class="w-full rounded-md bg-navy-900 text-white text-sm font-medium py-2.5 hover:bg-navy-800 transition">
                Create my account
            </button>
        </form>
        <p class="text-xs text-ink-400 mt-3">Next you'll save a card to start the trial.</p>
    @endif
@endsection
