@use('App\Support\Homepage\HomepageContent')
{{-- Shown only once at least 3 testimonials are published (see
     Testimonial::published()) — fully populated or absent, never sparse. --}}
@if ($testimonials->isNotEmpty())
    <section class="bg-surface-muted py-20 sm:py-28" aria-labelledby="testimonials-heading">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center" data-reveal>
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-emerald-600">Testimonials</p>
                <h2 id="testimonials-heading" class="mt-3 font-display text-3xl font-semibold tracking-tight text-navy-900 text-balance sm:text-4xl">{{ HomepageContent::text('home_testimonials_heading') }}</h2>
            </div>
            <div class="mt-14 columns-1 gap-5 md:columns-2 lg:columns-3">
                @foreach ($testimonials as $testimonial)
                    <figure class="mb-5 break-inside-avoid rounded-3xl bg-white p-7 ring-1 ring-line shadow-[0_12px_32px_-24px_rgba(11,30,61,.4)]" data-reveal>
                        @if ($testimonial->rating)
                            <div class="flex gap-0.5 text-gold-500" role="img" aria-label="Rated {{ $testimonial->rating }} out of 5">
                                @for ($i = 1; $i <= 5; $i++)
                                    <x-heroicon-s-star class="h-4 w-4 {{ $i <= $testimonial->rating ? '' : 'text-line' }}" aria-hidden="true" />
                                @endfor
                            </div>
                        @endif
                        <blockquote class="mt-4 text-[15px] leading-relaxed text-ink-900">&ldquo;{{ $testimonial->quote }}&rdquo;</blockquote>
                        <figcaption class="mt-6 flex items-center gap-3 border-t border-line pt-5">
                            @if ($testimonial->avatarUrl())
                                <img src="{{ $testimonial->avatarUrl() }}" alt="{{ $testimonial->author_name }}" class="h-11 w-11 rounded-full object-cover ring-2 ring-white" loading="lazy">
                            @else
                                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-navy-900 to-brand-600 text-sm font-semibold text-gold-300" aria-hidden="true">{{ strtoupper(substr($testimonial->author_name, 0, 1)) }}</span>
                            @endif
                            <div>
                                <div class="font-semibold text-navy-900">{{ $testimonial->author_name }}</div>
                                @if ($testimonial->author_role)
                                    <div class="text-xs text-ink-400">{{ $testimonial->author_role }}</div>
                                @endif
                            </div>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
@endif
