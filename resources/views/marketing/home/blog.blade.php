{{-- Latest articles from the WordPress blog (Content → Homepage → Blog
     articles) — see App\Services\Blog\BlogFeed. Silently absent when the
     feed is off, unconfigured or the blog can't be reached. --}}
@if ($blogPosts !== [])
    @php $blogBase = \App\Services\Blog\BlogFeed::baseUrl(); @endphp
    <section id="blog" class="bg-white py-20 sm:py-28" aria-labelledby="blog-heading">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between" data-reveal>
                <div class="max-w-2xl">
                    <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-rose-600">From the blog</p>
                    <h2 id="blog-heading" class="mt-3 font-display text-3xl font-semibold tracking-tight text-navy-900 text-balance sm:text-4xl">{{ \App\Services\Blog\BlogFeed::title() }}</h2>
                    <p class="mt-4 text-base text-ink-600 sm:text-lg">{{ \App\Services\Blog\BlogFeed::subtitle() }}</p>
                </div>
                <a href="{{ $blogBase }}" rel="noopener" class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-full px-5 py-2.5 text-sm font-semibold text-navy-900 ring-1 ring-line transition hover:bg-surface-muted sm:self-auto">
                    Visit our blog <x-heroicon-m-arrow-up-right class="h-4 w-4" aria-hidden="true" />
                </a>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($blogPosts as $post)
                    @php $accent = $accents[$loop->index % count($accents)]; @endphp
                    <article class="group relative flex flex-col overflow-hidden rounded-3xl bg-white ring-1 ring-line transition duration-300 hover:-translate-y-1 hover:shadow-[0_24px_48px_-24px_rgba(11,30,61,.35)]" data-reveal>
                        <div class="relative aspect-[16/9] overflow-hidden bg-surface-muted">
                            @if ($post['image'])
                                <img src="{{ $post['image'] }}" alt="{{ $post['image_alt'] }}" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-700 group-hover:scale-105 motion-reduce:transition-none">
                            @else
                                <div class="flex h-full w-full items-center justify-center bg-gradient-to-br {{ $accent['bar'] }}" aria-hidden="true">
                                    <x-heroicon-o-newspaper class="h-12 w-12 text-white/80" />
                                </div>
                            @endif
                            @if ($post['category'])
                                <span class="absolute left-4 top-4 rounded-full bg-white/95 px-3 py-1 text-xs font-semibold text-navy-900 shadow-sm">{{ $post['category'] }}</span>
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col p-6">
                            <p class="text-xs text-ink-400">
                                @if ($post['date'])
                                    <time datetime="{{ $post['date'] }}">{{ \Illuminate\Support\Carbon::parse($post['date'])->format('j M Y') }}</time>
                                @endif
                                @if ($post['author'])
                                    <span aria-hidden="true">·</span> {{ $post['author'] }}
                                @endif
                            </p>
                            <h3 class="mt-2 font-display text-lg font-semibold leading-snug text-navy-900">
                                <a href="{{ $post['url'] }}" rel="noopener" class="after:absolute after:inset-0 focus:outline-none focus-visible:underline">{{ $post['title'] }}</a>
                            </h3>
                            @if ($post['excerpt'])
                                <p class="mt-3 line-clamp-3 text-sm leading-relaxed text-ink-600">{{ $post['excerpt'] }}</p>
                            @endif
                            <span class="mt-auto inline-flex items-center gap-1.5 pt-5 text-sm font-semibold {{ $accent['text'] }}" aria-hidden="true">Read article <x-heroicon-m-arrow-right class="h-4 w-4 transition group-hover:translate-x-0.5" /></span>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
