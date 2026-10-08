@use('App\Support\Homepage\HomepageContent')
{{-- "How it works": the numbered journey, each step with a real screenshot
     of that step in AffilStack (Website → Homepage → How it works — upload
     your own per step to replace the shipped one). Desktop: steps on the
     left switch the large screenshot on the right; mobile: each step shows
     its screenshot underneath. --}}
@php
    $steps = HomepageContent::steps();
    $images = array_map(fn (array $step): ?string => HomepageContent::stepImage($step), $steps);
    $hasImages = array_filter($images) !== [];
@endphp

<section id="how-it-works" class="relative overflow-hidden bg-white py-20 sm:py-28" aria-labelledby="how-heading">
    <div class="pointer-events-none absolute -right-48 top-40 h-[28rem] w-[28rem] rounded-full bg-brand-100/50 blur-3xl" aria-hidden="true"></div>
    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-brand-600">{{ HomepageContent::text('home_how_eyebrow') }}</p>
            <h2 id="how-heading" class="mt-3 font-display text-3xl font-semibold tracking-tight text-navy-900 text-balance sm:text-4xl">
                {{ HomepageContent::highlight(HomepageContent::text('home_how_heading'), 'text-brand-600') }}
            </h2>
            <p class="mt-4 text-base text-ink-600 sm:text-lg">{{ HomepageContent::text('home_how_subheading') }}</p>
        </div>

        <div class="mt-14 grid items-start gap-10 {{ $hasImages ? 'lg:grid-cols-12' : '' }}" data-how-tabs>
            <ol class="{{ $hasImages ? 'lg:col-span-5' : 'grid gap-5 sm:grid-cols-2 lg:grid-cols-4' }} space-y-3" role="{{ $hasImages ? 'tablist' : 'list' }}" aria-orientation="vertical" aria-label="Steps">
                @foreach ($steps as $index => $step)
                    @php $accent = $accents[$index % count($accents)]; @endphp
                    <li class="list-none" data-reveal style="transition-delay: {{ $index * 80 }}ms" @if ($hasImages) role="presentation" @endif>
                        <div class="group relative rounded-3xl bg-white p-5 ring-1 ring-line transition duration-300 data-[active=true]:shadow-[0_24px_48px_-24px_rgba(11,30,61,.35)] data-[active=true]:ring-2 data-[active=true]:ring-brand-500/40" data-how-step="{{ $index }}" data-active="{{ $index === 0 ? 'true' : 'false' }}">
                            @if ($hasImages)
                                <button type="button" class="absolute inset-0 z-10 hidden rounded-3xl focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 lg:block" role="tab" id="how-tab-{{ $index }}" aria-controls="how-panel-{{ $index }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" tabindex="{{ $index === 0 ? '0' : '-1' }}" data-how-tab="{{ $index }}">
                                    <span class="sr-only">Step {{ $index + 1 }}: {{ $step['title'] }}</span>
                                </button>
                            @endif
                            <div class="flex items-start gap-4">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl ring-1 {{ $accent['tile'] }}">
                                    @include('marketing.home.partials.icon', ['icon' => $step['icon'] ?? null, 'class' => 'h-5 w-5'])
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-mono text-[10px] uppercase tracking-[0.18em] {{ $accent['text'] }}">Step {{ $index + 1 }}</p>
                                    <h3 class="mt-1 font-display text-lg font-semibold text-navy-900">{{ $step['title'] }}</h3>
                                    <p class="mt-1.5 text-sm leading-relaxed text-ink-600">{{ $step['description'] ?? '' }}</p>
                                    @if (! empty($step['link_url']) && \App\Support\SafeHtml::isSafeUrl($step['link_url']))
                                        <a href="{{ $step['link_url'] }}" class="relative z-20 mt-3 inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-brand-700">{{ HomepageContent::text('home_how_step_link_label') }} <span class="sr-only">— {{ $step['title'] }}</span><x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
                                    @endif
                                </div>
                            </div>
                            @if ($images[$index])
                                <x-marketing.screenshot :src="$images[$index]" :alt="$step['image_alt'] ?? $step['title']" class="mt-5 lg:hidden" />
                            @endif
                            <div class="absolute bottom-0 left-6 h-1 w-10 rounded-full bg-gradient-to-r opacity-0 transition-all duration-500 group-data-[active=true]:w-20 group-data-[active=true]:opacity-100 {{ $accent['bar'] }}" aria-hidden="true"></div>
                        </div>
                    </li>
                @endforeach
            </ol>

            @if ($hasImages)
                <div class="hidden lg:sticky lg:top-24 lg:col-span-7 lg:block" data-reveal>
                    @foreach ($steps as $index => $step)
                        <div id="how-panel-{{ $index }}" role="tabpanel" aria-labelledby="how-tab-{{ $index }}" @if ($index !== 0) hidden @endif data-how-panel="{{ $index }}">
                            @if ($images[$index])
                                <x-marketing.screenshot :src="$images[$index]" :alt="$step['image_alt'] ?? $step['title']" :eager="$index === 0" />
                            @endif
                            <p class="mt-5 text-center text-sm text-ink-600"><span class="font-semibold text-navy-900">Step {{ $index + 1 }}.</span> {{ $step['title'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <p class="mt-12 text-center text-sm text-ink-600" data-reveal>
            {{ HomepageContent::text('home_how_footer_text') }}
            <a href="{{ route('tutorials.index') }}" class="inline-flex items-center gap-1 font-semibold text-brand-600 underline-offset-4 hover:text-brand-700 hover:underline">{{ HomepageContent::text('home_how_footer_link') }} <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
        </p>
    </div>
</section>

@if ($hasImages)
    <script>
        (function () {
            var root = document.querySelector('[data-how-tabs]');
            if (! root) { return; }
            var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-how-tab]'));
            var select = function (index, focus) {
                tabs.forEach(function (tab, i) {
                    var on = i === index;
                    tab.setAttribute('aria-selected', on ? 'true' : 'false');
                    tab.tabIndex = on ? 0 : -1;
                    root.querySelector('[data-how-step="' + i + '"]').setAttribute('data-active', on ? 'true' : 'false');
                    root.querySelector('[data-how-panel="' + i + '"]').hidden = ! on;
                });
                if (focus) { tabs[index].focus(); }
            };
            tabs.forEach(function (tab, i) {
                tab.addEventListener('click', function () { select(i, false); });
                tab.addEventListener('mouseenter', function () { if (window.matchMedia('(hover: hover)').matches) { select(i, false); } });
                tab.addEventListener('keydown', function (event) {
                    var next = { ArrowDown: i + 1, ArrowRight: i + 1, ArrowUp: i - 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[event.key];
                    if (next === undefined) { return; }
                    event.preventDefault();
                    select((next + tabs.length) % tabs.length, true);
                });
            });
        })();
    </script>
@endif
