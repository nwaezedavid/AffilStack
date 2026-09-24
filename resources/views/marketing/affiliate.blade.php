@extends('layouts.marketing')

<?php
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
    $commissionRate = (int) round(config('referrals.commission_rate') * 100);
    $commissionRateDecimal = config('referrals.commission_rate');
    $cookieDays = config('referrals.cookie_days');
    $minimumPayout = number_format(config('referrals.minimum_payout_cents') / 100, 0);
    $payoutMethods = implode(' or ', config('referrals.payout_methods'));
?>

@section('title', 'Affiliate Program — Earn '.$commissionRate.'% recurring commission | '.$siteName)
@section('meta_description', 'Join the '.$siteName.' affiliate program and earn '.$commissionRate.'% recurring commission for every customer you refer, for as long as they stay subscribed. Apply free in two minutes — no purchase required.')

@section('content')
    {{-- JSON-LD: helps this page surface for "[product] affiliate program" searches. --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name' => 'Do I need to be a '.$siteName.' customer to become an affiliate?',
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'No. Anyone can apply to the affiliate program, whether or not they use '.$siteName.' themselves. If you\'re already a customer, you\'re automatically an affiliate — just log in to find your referral link.'],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'How much commission do affiliates earn?',
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Affiliates earn '.$commissionRate.'% commission on every payment a referred customer makes, for as long as they stay subscribed — not just the first payment.'],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'When do affiliates get paid?',
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Once your approved commission balance reaches $'.$minimumPayout.', you can request a payout via '.$payoutMethods.' from your affiliate dashboard.'],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>

    <section class="max-w-5xl mx-auto px-6 pt-16 pb-12 text-center">
        <span class="inline-block text-xs font-semibold tracking-wide uppercase text-gold-600 bg-gold-50 border border-gold-200 rounded-full px-3 py-1 mb-5">
            {{ $siteName }} Affiliate Program
        </span>
        <h1 class="font-display font-semibold text-4xl sm:text-5xl text-navy-900 text-wrap-balance">
            Turn your audience into {{ $commissionRate }}% recurring income
        </h1>
        <p class="text-ink-600 mt-4 max-w-2xl mx-auto text-lg">
            Every customer you refer to {{ $siteName }} pays you {{ $commissionRate }}% of what they spend — every
            single month, for as long as they stay subscribed. One referral this year can quietly pay you for years
            to come.
        </p>
        <div class="flex flex-wrap items-center justify-center gap-3 mt-8">
            <a href="#apply" class="rounded-md bg-navy-900 text-white px-6 py-3 text-sm font-medium hover:bg-navy-800 transition">
                Apply free — takes 2 minutes
            </a>
            <a href="#calculator" class="rounded-md border border-line px-6 py-3 text-sm font-medium text-navy-900 hover:bg-surface-muted transition">
                Calculate your earnings
            </a>
        </div>
        <p class="text-sm text-ink-400 mt-5">
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

    {{-- Earnings calculator --}}
    <section id="calculator" class="max-w-4xl mx-auto px-6 py-16">
        <div class="text-center mb-10">
            <h2 class="font-display font-semibold text-3xl text-navy-900 text-wrap-balance">See what your referrals could be worth</h2>
            <p class="text-ink-600 mt-3 max-w-xl mx-auto">
                Because commission is recurring, your income compounds as you keep referring — this isn't a
                one-off bonus, it's a growing monthly paycheck.
            </p>
        </div>

        <div
            class="bg-surface border border-line rounded-xl p-6 sm:p-8"
            id="earnings-calculator"
            data-commission-rate="{{ $commissionRateDecimal }}"
            data-plans='@json($calculatorPlans)'
        >
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
                <div>
                    <label for="calc-plan" class="block text-sm font-medium text-navy-900 mb-1">Plan your referrals typically choose</label>
                    <select id="calc-plan" class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        @foreach ($calculatorPlans as $index => $plan)
                            <option value="{{ $index }}" @selected($plan['isFeatured'])>{{ $plan['name'] }} — ${{ number_format($plan['priceMonthly'], 0) }}/mo</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="calc-referrals" class="block text-sm font-medium text-navy-900 mb-1">
                        New referrals per month: <span id="calc-referrals-value" class="text-brand-600 font-semibold">5</span>
                    </label>
                    <input type="range" id="calc-referrals" min="1" max="30" value="5" step="1"
                        class="w-full accent-brand-600 mt-3">
                    <div class="flex justify-between text-xs text-ink-400 mt-1">
                        <span>1</span>
                        <span>30</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
                <div class="rounded-lg bg-surface-muted p-4">
                    <div class="text-xs uppercase tracking-wide text-ink-400 mb-1">Month 1</div>
                    <div class="font-display font-semibold text-2xl text-navy-900" id="calc-month-1">$0</div>
                    <p class="text-xs text-ink-500 mt-1">recurring per month</p>
                </div>
                <div class="rounded-lg bg-surface-muted p-4">
                    <div class="text-xs uppercase tracking-wide text-ink-400 mb-1">Month 6</div>
                    <div class="font-display font-semibold text-2xl text-navy-900" id="calc-month-6">$0</div>
                    <p class="text-xs text-ink-500 mt-1">recurring per month</p>
                </div>
                <div class="rounded-lg bg-gold-50 border border-gold-200 p-4">
                    <div class="text-xs uppercase tracking-wide text-gold-700 mb-1">Month 12</div>
                    <div class="font-display font-semibold text-2xl text-navy-900" id="calc-month-12">$0</div>
                    <p class="text-xs text-ink-500 mt-1">recurring per month</p>
                </div>
            </div>

            <div class="mt-6 text-center border-t border-line pt-6">
                <p class="text-sm text-ink-600">Estimated total earned across your first 12 months</p>
                <div class="font-display font-semibold text-3xl text-navy-900 mt-1" id="calc-year-total">$0</div>
            </div>

            <p class="text-xs text-ink-400 mt-6 text-center max-w-xl mx-auto">
                Illustrative estimate only. Assumes each referral subscribes at the plan price shown and stays
                subscribed for the full period, with no discounts or churn. Actual earnings depend on the plans
                your referrals choose and how long they stay.
            </p>
        </div>
    </section>

    <section class="max-w-5xl mx-auto px-6 py-16 border-t border-line">
        <h2 class="font-display font-semibold text-2xl text-navy-900 text-center mb-10">How it works</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="mx-auto h-10 w-10 rounded-full bg-navy-900 text-white flex items-center justify-center font-semibold mb-3">1</div>
                <h3 class="font-semibold text-navy-900 mb-1">Apply</h3>
                <p class="text-sm text-ink-600">Tell us a bit about how you'll promote {{ $siteName }} — no account or purchase needed.</p>
            </div>
            <div class="text-center">
                <div class="mx-auto h-10 w-10 rounded-full bg-navy-900 text-white flex items-center justify-center font-semibold mb-3">2</div>
                <h3 class="font-semibold text-navy-900 mb-1">Get approved</h3>
                <p class="text-sm text-ink-600">We review every application by hand — once approved, you'll get an email to set up your account and get your own affiliate dashboard.</p>
            </div>
            <div class="text-center">
                <div class="mx-auto h-10 w-10 rounded-full bg-navy-900 text-white flex items-center justify-center font-semibold mb-3">3</div>
                <h3 class="font-semibold text-navy-900 mb-1">Share & earn</h3>
                <p class="text-sm text-ink-600">Grab your personal referral link and start earning {{ $commissionRate }}% on every customer you bring in.</p>
            </div>
        </div>
    </section>

    <section class="bg-surface-muted border-y border-line">
        <div class="max-w-5xl mx-auto px-6 py-16">
            <h2 class="font-display font-semibold text-2xl text-navy-900 text-center mb-10">Built for people with an audience</h2>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-6 text-center">
                <div>
                    <div class="text-2xl mb-2">✍️</div>
                    <h3 class="font-semibold text-navy-900 text-sm mb-1">Bloggers & writers</h3>
                    <p class="text-xs text-ink-600">Link {{ $siteName }} from a review, tutorial, or resource page.</p>
                </div>
                <div>
                    <div class="text-2xl mb-2">🎥</div>
                    <h3 class="font-semibold text-navy-900 text-sm mb-1">YouTubers & creators</h3>
                    <p class="text-xs text-ink-600">Mention your link in videos, descriptions, or pinned comments.</p>
                </div>
                <div>
                    <div class="text-2xl mb-2">📧</div>
                    <h3 class="font-semibold text-navy-900 text-sm mb-1">Newsletter owners</h3>
                    <p class="text-xs text-ink-600">Recommend {{ $siteName }} to a list that already trusts you.</p>
                </div>
                <div>
                    <div class="text-2xl mb-2">💬</div>
                    <h3 class="font-semibold text-navy-900 text-sm mb-1">Communities & groups</h3>
                    <p class="text-xs text-ink-600">Share it in the Slack, Discord, or forum you help run.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="apply" class="border-t border-line">
        <div class="max-w-3xl mx-auto px-6 py-16">
            <div class="text-center mb-10">
                <h2 class="font-display font-semibold text-3xl text-navy-900 text-wrap-balance">Apply to become an affiliate</h2>
                <p class="text-ink-600 mt-3">Takes two minutes. We'll email you once it's reviewed.</p>
            </div>

            <form method="POST" action="{{ route('affiliate.apply') }}" class="bg-surface border border-line rounded-lg p-6 sm:p-8 space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
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

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-navy-900 mb-1">Phone (optional)</label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
                            class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="website_url" class="block text-sm font-medium text-navy-900 mb-1">Website, YouTube, or social profile (optional)</label>
                        <input type="url" name="website_url" id="website_url" value="{{ old('website_url') }}" placeholder="https://"
                            class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        @error('website_url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="audience_size" class="block text-sm font-medium text-navy-900 mb-1">Audience size</label>
                        <select name="audience_size" id="audience_size" required
                            class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="" disabled {{ old('audience_size') ? '' : 'selected' }}>Select one</option>
                            @foreach ($audienceSizeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('audience_size') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('audience_size')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="experience_level" class="block text-sm font-medium text-navy-900 mb-1">Affiliate marketing experience</label>
                        <select name="experience_level" id="experience_level" required
                            class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="" disabled {{ old('experience_level') ? '' : 'selected' }}>Select one</option>
                            @foreach ($experienceLevelOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('experience_level') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('experience_level')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
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

    @push('head')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var root = document.getElementById('earnings-calculator');
                if (!root) return;

                var commissionRate = parseFloat(root.dataset.commissionRate);
                var plans = JSON.parse(root.dataset.plans || '[]');
                var planSelect = document.getElementById('calc-plan');
                var referralsInput = document.getElementById('calc-referrals');
                var referralsValue = document.getElementById('calc-referrals-value');
                var month1 = document.getElementById('calc-month-1');
                var month6 = document.getElementById('calc-month-6');
                var month12 = document.getElementById('calc-month-12');
                var yearTotal = document.getElementById('calc-year-total');

                function money(n) {
                    return '$' + Math.round(n).toLocaleString('en-US');
                }

                function recalculate() {
                    var plan = plans[parseInt(planSelect.value, 10)] || plans[0];
                    if (!plan) return;

                    var referralsPerMonth = parseInt(referralsInput.value, 10);
                    referralsValue.textContent = referralsPerMonth;

                    var commissionPerReferral = plan.priceMonthly * commissionRate;

                    // Referred customers accumulate month over month (no
                    // churn, illustrative only — see disclaimer), so
                    // recurring monthly commission at month N is
                    // referrals * N * commissionPerReferral.
                    var mrrAt = function (n) {
                        return referralsPerMonth * n * commissionPerReferral;
                    };

                    month1.textContent = money(mrrAt(1));
                    month6.textContent = money(mrrAt(6));
                    month12.textContent = money(mrrAt(12));

                    var totalYearOne = 0;
                    for (var n = 1; n <= 12; n++) {
                        totalYearOne += mrrAt(n);
                    }
                    yearTotal.textContent = money(totalYearOne);
                }

                planSelect.addEventListener('change', recalculate);
                referralsInput.addEventListener('input', recalculate);
                recalculate();
            });
        </script>
    @endpush
@endsection
