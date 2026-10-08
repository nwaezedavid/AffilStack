{{-- The closing navy banner shared by the features pages. --}}
<section class="bg-white py-20 sm:py-24" aria-labelledby="features-cta-heading">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="relative isolate overflow-hidden rounded-[2.5rem] bg-navy-950 px-6 py-16 text-center shadow-[0_40px_80px_-40px_rgba(11,30,61,.8)] sm:px-16 sm:py-20" data-reveal>
            <div class="absolute inset-0 -z-10" aria-hidden="true" style="background: radial-gradient(60% 80% at 50% 0%, rgba(36,82,217,.6) 0%, transparent 70%), radial-gradient(40% 60% at 100% 100%, rgba(201,162,74,.45) 0%, transparent 70%), radial-gradient(40% 60% at 0% 100%, rgba(16,185,129,.25) 0%, transparent 70%);"></div>
            <div class="home-grid-pattern absolute inset-0 -z-10" aria-hidden="true"></div>
            <h2 id="features-cta-heading" class="mx-auto max-w-2xl font-display text-3xl font-semibold tracking-tight text-white text-balance sm:text-5xl">
                {{ \App\Support\Homepage\HomepageContent::highlight($heading, 'text-gold-300') }}
            </h2>
            @if (filled($text))
                <p class="mx-auto mt-5 max-w-xl text-base text-white/75 sm:text-lg">{{ $text }}</p>
            @endif
            <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="{{ $url }}" class="group inline-flex w-full items-center justify-center gap-2 rounded-full bg-gold-400 px-8 py-4 text-sm font-semibold text-navy-950 shadow-[0_12px_32px_-12px_rgba(211,168,84,.9)] transition hover:bg-gold-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-navy-950 sm:w-auto">
                    {{ $label }}
                    <x-heroicon-m-arrow-right class="h-4 w-4 transition group-hover:translate-x-0.5" aria-hidden="true" />
                </a>
                <a href="{{ \App\Support\Website\NavigationMenu::expertUrl() }}" class="inline-flex w-full items-center justify-center rounded-full px-8 py-4 text-sm font-semibold text-white ring-1 ring-white/25 transition hover:bg-white/10 sm:w-auto">{{ \App\Support\Website\NavigationMenu::label('nav_expert_label') }}</a>
            </div>
        </div>
    </div>
</section>
