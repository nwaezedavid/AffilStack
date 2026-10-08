@use('App\Support\Homepage\HomepageContent')
@php $stats = HomepageContent::stats(); @endphp

<section id="results" class="bg-white py-16 sm:py-20" aria-labelledby="stats-heading">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-navy-900 via-navy-800 to-brand-700 px-6 py-12 text-white shadow-[0_30px_60px_-30px_rgba(11,30,61,.6)] sm:px-12" data-reveal>
            <div class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-gold-400/25 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-24 left-10 h-64 w-64 rounded-full bg-sky-400/20 blur-3xl" aria-hidden="true"></div>
            <h2 id="stats-heading" class="relative max-w-xl font-display text-2xl font-semibold tracking-tight text-balance sm:text-3xl">
                {{ HomepageContent::highlight(HomepageContent::text('home_stats_heading'), 'text-gold-300') }}
            </h2>
            <dl class="relative mt-10 grid grid-cols-2 gap-x-6 gap-y-10 {{ count($stats) >= 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-'.count($stats) }}">
                @foreach ($stats as $stat)
                    <div class="flex flex-col border-l-2 border-gold-400/70 pl-4 sm:pl-5">
                        <dt class="order-2 mt-2 text-sm leading-snug text-white/70">{{ $stat['label'] ?? '' }}</dt>
                        <dd class="order-1 whitespace-nowrap font-display text-3xl font-semibold tracking-tight text-white sm:text-5xl">{{ $stat['value'] ?? '' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>
</section>
