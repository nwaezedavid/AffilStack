@use('App\Support\Homepage\HomepageContent')
{{-- Pricing teaser — prices come from Plan, never hardcoded, so this can't
     drift from the real /pricing page. --}}
@if ($plans->isNotEmpty())
    <section id="pricing" class="bg-white py-20 sm:py-28" aria-labelledby="pricing-heading">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center" data-reveal>
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-amber-600">Pricing</p>
                <h2 id="pricing-heading" class="mt-3 font-display text-3xl font-semibold tracking-tight text-navy-900 text-balance sm:text-4xl">{{ HomepageContent::text('home_pricing_heading') }}</h2>
                <p class="mt-4 text-base text-ink-600 sm:text-lg">{{ HomepageContent::text('home_pricing_subheading') }}</p>
            </div>

            <div class="mx-auto mt-14 grid max-w-5xl gap-5 md:grid-cols-3 md:items-center">
                @foreach ($plans as $plan)
                    @if ($plan->is_featured)
                        <div class="relative flex flex-col rounded-3xl bg-gradient-to-b from-navy-900 to-navy-950 p-8 text-white shadow-[0_30px_60px_-30px_rgba(11,30,61,.7)] ring-2 ring-gold-400 md:py-10" data-reveal>
                            <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-gold-400 px-3 py-1 text-xs font-semibold text-navy-950 shadow">Most popular</span>
                            <h3 class="font-display text-lg font-semibold">{{ $plan->name }}</h3>
                            @if ($plan->description)
                                <p class="mt-1 text-sm text-white/60">{{ $plan->description }}</p>
                            @endif
                            <p class="mt-6"><span class="font-display text-5xl font-semibold tracking-tight">${{ number_format($plan->priceMonthly(), 0) }}</span><span class="text-white/50">/mo</span></p>
                            <p class="mt-2 inline-flex items-center gap-2 text-sm text-gold-300"><x-heroicon-m-bolt class="h-4 w-4" aria-hidden="true" /> {{ number_format($plan->credits_per_month) }} credits/month</p>
                            <a href="{{ route('registration.form', $plan) }}" class="mt-8 rounded-full bg-gold-400 py-3 text-center text-sm font-semibold text-navy-950 transition hover:bg-gold-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">Get started</a>
                        </div>
                    @else
                        <div class="flex flex-col rounded-3xl bg-white p-8 ring-1 ring-line" data-reveal>
                            <h3 class="font-display text-lg font-semibold text-navy-900">{{ $plan->name }}</h3>
                            @if ($plan->description)
                                <p class="mt-1 text-sm text-ink-600">{{ $plan->description }}</p>
                            @endif
                            <p class="mt-6"><span class="font-display text-4xl font-semibold tracking-tight text-navy-900">${{ number_format($plan->priceMonthly(), 0) }}</span><span class="text-ink-400">/mo</span></p>
                            <p class="mt-2 inline-flex items-center gap-2 text-sm text-ink-600"><x-heroicon-m-bolt class="h-4 w-4 text-brand-600" aria-hidden="true" /> {{ number_format($plan->credits_per_month) }} credits/month</p>
                            <a href="{{ route('registration.form', $plan) }}" class="mt-8 rounded-full py-3 text-center text-sm font-semibold text-navy-900 ring-1 ring-line transition hover:bg-surface-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">Get started</a>
                        </div>
                    @endif
                @endforeach
            </div>

            <p class="mt-10 text-center" data-reveal>
                <a href="{{ route('registration.pricing') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 underline-offset-4 hover:text-brand-700 hover:underline">See the full plan comparison <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
            </p>
        </div>
    </section>
@endif
