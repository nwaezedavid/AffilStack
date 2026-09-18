@extends('layouts.marketing')

<?php
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
    $commissionRate = (int) round(config('referrals.commission_rate') * 100);
    $cookieDays = config('referrals.cookie_days');
    $minimumPayout = number_format(config('referrals.minimum_payout_cents') / 100, 0);
    $payoutMethods = implode(' or ', config('referrals.payout_methods'));
?>

@section('title', 'Become an affiliate — '.$siteName)
@section('meta_description', 'Earn '.$commissionRate.'% recurring commission promoting '.$siteName.'. Apply free — no purchase required.')

@section('content')
    <section class="max-w-5xl mx-auto px-6 pt-16 pb-12 text-center">
        <span class="inline-block text-xs font-semibold tracking-wide uppercase text-gold-600 bg-gold-50 border border-gold-200 rounded-full px-3 py-1 mb-5">
            {{ $siteName }} Affiliate Program
        </span>
        <h1 class="font-display font-semibold text-4xl sm:text-5xl text-navy-900 text-wrap-balance">
            Earn {{ $commissionRate }}% recurring commission for every customer you refer
        </h1>
        <p class="text-ink-600 mt-4 max-w-2xl mx-auto text-lg">
            Apply below — no purchase required. Once approved, you get a personal referral link, a {{ $cookieDays }}-day
            tracking window, and a dashboard to watch your earnings grow.
        </p>
        <p class="text-sm text-ink-400 mt-4">
            Already a {{ $siteName }} customer? You're an affiliate automatically —
            <a href="{{ route('login') }}" class="text-brand-600 underline">log in</a> to find your referral link.
        </p>
    </section>

    <section class="border-y border-line bg-surface-muted">
        <div class="max-w-5xl mx-auto px-6 py-10 grid sm:grid-cols-3 gap-6 text-center">
            <div>
                <div class="font-display font-semibold text-3xl text-navy-900">{{ $commissionRate }}%</div>
                <p class="text-sm text-ink-600 mt-1">Commission on every payment your referral makes, for as long as they stay subscribed</p>
            </div>
            <div>
                <div class="font-display font-semibold text-3xl text-navy-900">{{ $cookieDays }} days</div>
                <p class="text-sm text-ink-600 mt-1">A visitor you refer still counts as yours even if they sign up weeks later</p>
            </div>
            <div>
                <div class="font-display font-semibold text-3xl text-navy-900">${{ $minimumPayout }}</div>
                <p class="text-sm text-ink-600 mt-1">Minimum balance to request a payout via {{ $payoutMethods }}</p>
            </div>
        </div>
    </section>

    <section class="max-w-5xl mx-auto px-6 py-16">
        <h2 class="font-display font-semibold text-2xl text-navy-900 text-center mb-10">How it works</h2>
        <div class="grid sm:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="mx-auto h-10 w-10 rounded-full bg-navy-900 text-white flex items-center justify-center font-semibold mb-3">1</div>
                <h3 class="font-semibold text-navy-900 mb-1">Apply</h3>
                <p class="text-sm text-ink-600">Tell us a bit about how you'll promote {{ $siteName }} — no account or purchase needed.</p>
            </div>
            <div class="text-center">
                <div class="mx-auto h-10 w-10 rounded-full bg-navy-900 text-white flex items-center justify-center font-semibold mb-3">2</div>
                <h3 class="font-semibold text-navy-900 mb-1">Get approved</h3>
                <p class="text-sm text-ink-600">We review every application by hand — once approved, you'll get an email to set up your account.</p>
            </div>
            <div class="text-center">
                <div class="mx-auto h-10 w-10 rounded-full bg-navy-900 text-white flex items-center justify-center font-semibold mb-3">3</div>
                <h3 class="font-semibold text-navy-900 mb-1">Share & earn</h3>
                <p class="text-sm text-ink-600">Grab your personal referral link and start earning {{ $commissionRate }}% on every customer you bring in.</p>
            </div>
        </div>
    </section>

    <section class="bg-surface-muted border-t border-line">
        <div class="max-w-3xl mx-auto px-6 py-16">
            <div class="text-center mb-10">
                <h2 class="font-display font-semibold text-3xl text-navy-900 text-wrap-balance">Apply to become an affiliate</h2>
                <p class="text-ink-600 mt-3">Takes two minutes. We'll email you once it's reviewed.</p>
            </div>

            <form method="POST" action="{{ route('affiliate.apply') }}" class="bg-surface border border-line rounded-lg p-6 sm:p-8 space-y-5">
                @csrf

                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label for="name" class="block text-sm font-medium text-navy-900 mb-1">Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                            class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-navy-900 mb-1">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required
                            class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-navy-900 mb-1">Phone (optional)</label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
                        class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="promotion_channels" class="block text-sm font-medium text-navy-900 mb-1">How will you promote {{ $siteName }}?</label>
                    <textarea name="promotion_channels" id="promotion_channels" rows="3" required
                        placeholder="e.g. YouTube channel, blog, email list, paid ads..."
                        class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">{{ old('promotion_channels') }}</textarea>
                    @error('promotion_channels')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="message" class="block text-sm font-medium text-navy-900 mb-1">Anything else we should know? (optional)</label>
                    <textarea name="message" id="message" rows="3"
                        class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">{{ old('message') }}</textarea>
                    @error('message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="w-full sm:w-auto rounded-md bg-navy-900 text-white px-6 py-3 text-sm font-medium hover:bg-navy-800 transition">
                    Submit application
                </button>
            </form>
        </div>
    </section>
@endsection
