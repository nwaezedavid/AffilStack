@use('App\Support\Homepage\HomepageContent')
@php $steps = HomepageContent::steps(); @endphp

<section id="how-it-works" class="relative bg-white py-20 sm:py-28" aria-labelledby="how-heading">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-brand-600">How it works</p>
            <h2 id="how-heading" class="mt-3 font-display text-3xl font-semibold tracking-tight text-navy-900 text-balance sm:text-4xl">
                {{ HomepageContent::highlight(HomepageContent::text('home_how_heading'), 'text-brand-600') }}
            </h2>
            <p class="mt-4 text-base text-ink-600 sm:text-lg">{{ HomepageContent::text('home_how_subheading') }}</p>
        </div>

        <div class="relative mt-14">
            @if (count($steps) <= 4)
                <div class="pointer-events-none absolute left-[12%] right-[12%] top-12 hidden h-px lg:block" aria-hidden="true" style="background-image: linear-gradient(90deg, #c7d2fe, #ddd6fe, #a7f3d0, #fde68a);"></div>
            @endif
        <ol class="relative grid gap-5 sm:grid-cols-2 {{ count($steps) >= 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-'.count($steps) }}" role="list">
            @foreach ($steps as $index => $step)
                @php $accent = $accents[$index % count($accents)]; @endphp
                <li class="group relative rounded-3xl bg-white p-6 ring-1 ring-line transition duration-300 hover:-translate-y-1 hover:shadow-[0_24px_48px_-24px_rgba(11,30,61,.35)]" data-reveal style="transition-delay: {{ $index * 80 }}ms">
                    <div class="flex items-center justify-between">
                        <span class="relative flex h-12 w-12 items-center justify-center rounded-2xl ring-1 {{ $accent['tile'] }}">
                            @include('marketing.home.partials.icon', ['icon' => $step['icon'] ?? null, 'class' => 'h-6 w-6'])
                        </span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full font-display text-sm font-semibold text-white {{ $accent['number'] }}">{{ $index + 1 }}</span>
                    </div>
                    <h3 class="mt-6 font-display text-lg font-semibold text-navy-900">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink-600">{{ $step['description'] ?? '' }}</p>
                    <div class="mt-6 h-1 w-12 rounded-full bg-gradient-to-r transition-all duration-500 group-hover:w-24 {{ $accent['bar'] }}" aria-hidden="true"></div>
                </li>
            @endforeach
        </ol>
        </div>

        <p class="mt-12 text-center text-sm text-ink-600" data-reveal>
            Want a closer look?
            <a href="{{ route('tutorials.index') }}" class="inline-flex items-center gap-1 font-semibold text-brand-600 hover:text-brand-700 underline-offset-4 hover:underline">Watch free tutorials in our Learning Centre <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
        </p>
    </div>
</section>
