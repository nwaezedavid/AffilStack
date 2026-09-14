@extends('layouts.marketing')

@section('title', 'Get started with '.$plan->name.' · '.(\App\Models\SiteSetting::get('site_name', 'AffilStack')))

@section('content')
    <div class="max-w-md mx-auto px-6 py-14">
        <div class="bg-surface border border-line rounded-xl shadow-sm p-8">
            <div class="mb-6">
                <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">You picked</div>
                <div class="font-display font-semibold text-lg text-navy-900">{{ $plan->name }} — ${{ number_format($plan->priceMonthly()) }}/mo</div>
                <p class="text-xs text-ink-600 mt-1">{{ $plan->description }}</p>
            </div>

            <form method="POST" action="{{ route('registration.store', $plan) }}" class="space-y-4">
                @csrf
                <div>
                    <label for="name" class="block text-sm font-medium text-ink-900 mb-1">Full name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                           class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-ink-900 mb-1">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required
                           class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
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
                <div>
                    <label class="block text-sm font-medium text-ink-900 mb-1">Billing cycle</label>
                    <select name="billing_cycle" class="w-full rounded-md border border-line px-3 py-2 text-sm">
                        <option value="monthly">Monthly — ${{ number_format($plan->priceMonthly()) }}/mo</option>
                        <option value="yearly">Yearly — ${{ number_format($plan->priceYearly()) }}/yr (2 months free)</option>
                    </select>
                </div>
                @if (count($enabledGateways) > 1)
                    <div>
                        <label class="block text-sm font-medium text-ink-900 mb-1">Payment method</label>
                        <select name="gateway" class="w-full rounded-md border border-line px-3 py-2 text-sm">
                            @foreach ($enabledGateways as $gateway)
                                <option value="{{ $gateway->key() }}">{{ $gateway->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif (count($enabledGateways) === 1)
                    <input type="hidden" name="gateway" value="{{ $enabledGateways[0]->key() }}">
                @endif
                <button type="submit" class="w-full rounded-md bg-navy-900 text-white text-sm font-medium py-2.5 hover:bg-navy-800 transition">
                    Continue to secure payment
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-ink-600">
                Already have an account? <a href="{{ route('login') }}" class="text-brand-600 hover:text-brand-700 font-medium">Sign in</a>
            </p>
        </div>
    </div>
@endsection
