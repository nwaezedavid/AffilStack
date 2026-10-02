@use('App\Support\Homepage\HomepageContent')
{{-- The first few published FAQs (Support → FAQ items); the full list and
     its FAQPage schema live on /help. --}}
@if ($faqs->isNotEmpty())
    <section id="faq" class="bg-surface-muted py-20 sm:py-28" aria-labelledby="faq-heading">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
            <div class="lg:col-span-4" data-reveal>
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-sky-600">FAQ</p>
                <h2 id="faq-heading" class="mt-3 font-display text-3xl font-semibold tracking-tight text-navy-900 text-balance sm:text-4xl">{{ HomepageContent::text('home_faq_heading') }}</h2>
                <p class="mt-4 text-base text-ink-600">{{ HomepageContent::text('home_faq_subheading') }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('help.index') }}" class="inline-flex items-center gap-2 rounded-full bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800">
                        <x-heroicon-m-lifebuoy class="h-4 w-4 text-gold-300" aria-hidden="true" /> Visit the Help Centre
                    </a>
                    <a href="{{ route('contact.show') }}" class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold text-navy-900 ring-1 ring-line bg-white transition hover:bg-surface-muted">Ask us anything</a>
                </div>
            </div>
            <div class="space-y-3 lg:col-span-8" data-reveal>
                @foreach ($faqs as $faq)
                    <details class="group rounded-2xl bg-white ring-1 ring-line transition open:shadow-[0_16px_40px_-28px_rgba(11,30,61,.45)] open:ring-brand-200" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-2xl px-6 py-5 font-display text-base font-semibold text-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 [&::-webkit-details-marker]:hidden">
                            {{ $faq->question }}
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-surface-muted text-navy-900 transition group-open:rotate-45 group-open:bg-brand-600 group-open:text-white" aria-hidden="true">
                                <x-heroicon-m-plus class="h-4 w-4" />
                            </span>
                        </summary>
                        <div class="px-6 pb-6 text-sm leading-relaxed text-ink-600">{{ $faq->answer }}</div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
@endif
