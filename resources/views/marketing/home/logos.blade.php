@use('App\Support\Homepage\HomepageContent')
{{-- Brand logos marquee — admin-managed (Content → Brand Logos). The list is
     duplicated once so the CSS animation loops seamlessly. --}}
@if ($brandLogos->isNotEmpty())
    <section class="border-b border-line bg-white py-10" aria-label="Brands we've worked with">
        <p class="text-center font-mono text-[11px] uppercase tracking-[0.2em] text-ink-400">{{ HomepageContent::text('home_logos_heading') }}</p>
        <div class="marquee-mask mt-6 overflow-hidden">
            <div class="marquee-track flex items-center gap-14">
                @foreach ($brandLogos->concat($brandLogos) as $logo)
                    @php $img = '<img src="'.e($logo->logoUrl()).'" alt="'.($loop->index >= $brandLogos->count() ? '' : e($logo->name)).'" class="h-8 w-auto object-contain grayscale opacity-60 transition hover:opacity-100 hover:grayscale-0" loading="lazy">'; @endphp
                    @if ($logo->url && \App\Support\SafeHtml::isSafeUrl($logo->url))
                        <a href="{{ $logo->url }}" target="_blank" rel="noopener sponsored" class="shrink-0" @if ($loop->index >= $brandLogos->count()) tabindex="-1" aria-hidden="true" @endif>{!! $img !!}</a>
                    @else
                        <span class="shrink-0" @if ($loop->index >= $brandLogos->count()) aria-hidden="true" @endif>{!! $img !!}</span>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
@endif
