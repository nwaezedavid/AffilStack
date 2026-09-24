@extends('layouts.app')

@section('title', 'Buy Credits')

@section('content')
    <p class="text-sm text-ink-600 max-w-lg mb-6">
        Ran out of credits before your next renewal? Buy a top-up pack any time — it's added to your balance
        instantly and never expires.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-3xl">
        @foreach ($packages as $package)
            <div class="bg-surface border rounded-lg p-5 flex flex-col gap-3 {{ $package->is_featured ? 'border-gold-500 ring-1 ring-gold-500' : 'border-line' }}">
                @if ($package->is_featured)
                    <span class="text-xs font-mono uppercase text-gold-600">Best value</span>
                @endif
                <div class="font-display font-semibold text-base text-navy-900">{{ $package->name }}</div>
                <div class="font-mono text-2xl font-semibold text-ink-900">{{ number_format($package->credits) }}<span class="text-xs font-sans text-ink-400"> credits</span></div>
                <div class="text-lg font-semibold text-navy-900">${{ number_format($package->price(), 2) }}</div>
                <p class="text-xs text-ink-400">${{ number_format($package->pricePerCredit(), 4) }} per credit</p>
                @if ($package->description)
                    <p class="text-xs text-ink-600">{{ $package->description }}</p>
                @endif

                <form method="POST" action="{{ route('credit-topups.checkout', $package) }}" class="space-y-2 mt-auto">
                    @csrf
                    @if (count($enabledGateways) > 1)
                        <select name="gateway" class="w-full rounded-md border border-line px-2 py-1.5 text-xs">
                            @foreach ($enabledGateways as $gateway)
                                <option value="{{ $gateway->key() }}">{{ $gateway->label() }}</option>
                            @endforeach
                        </select>
                    @elseif (count($enabledGateways) === 1)
                        <input type="hidden" name="gateway" value="{{ $enabledGateways[0]->key() }}">
                    @endif
                    <button class="w-full rounded-md bg-navy-900 text-white text-sm py-2 hover:bg-navy-800 transition">
                        Buy now
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    @if ($packages->isEmpty())
        <p class="text-sm text-ink-400">No top-up packages are available right now.</p>
    @endif

    <p class="text-xs text-ink-400 mt-8 max-w-lg">
        Need credits every month instead of a one-time boost? A higher plan might work out cheaper per credit —
        see <a href="{{ route('billing.index') }}" class="text-brand-600 underline">Billing &amp; Plan</a>.
    </p>
@endsection
