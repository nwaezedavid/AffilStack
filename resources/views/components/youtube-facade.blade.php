@props(['url', 'title' => 'Play video', 'poster' => null, 'posterAlt' => '', 'playSize' => 'lg'])

{{-- A lightweight click-to-play YouTube player: only YouTube's thumbnail
     (or an admin-uploaded poster) loads with the page; the privacy-enhanced
     youtube-nocookie iframe is swapped in when the visitor presses play.
     Any YouTube URL shape works — see App\Support\YouTubeUrl. --}}
@php
    $embedUrl = \App\Support\YouTubeUrl::embedUrl($url);
    $thumbnail = $poster ?: \App\Support\YouTubeUrl::thumbnailUrl($url);
    $playClasses = $playSize === 'sm' ? 'h-12 w-12' : 'h-16 w-16 sm:h-20 sm:w-20';
@endphp

@if ($embedUrl)
    <div {{ $attributes->merge(['class' => 'relative aspect-video overflow-hidden bg-navy-950']) }}>
        <button
            type="button"
            class="group absolute inset-0 h-full w-full cursor-pointer focus:outline-none focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-gold-400"
            data-youtube-facade
            data-embed="{{ $embedUrl }}"
            data-title="{{ $title }}"
            aria-label="Play video: {{ $title }}"
        >
            @if ($thumbnail)
                <img src="{{ $thumbnail }}" alt="{{ $posterAlt }}" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-[1.03] motion-reduce:transition-none">
            @endif
            <span class="absolute inset-0 bg-gradient-to-t from-navy-950/70 via-navy-950/10 to-transparent" aria-hidden="true"></span>
            <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                <span class="{{ $playClasses }} flex items-center justify-center rounded-full bg-white text-navy-950 shadow-2xl ring-8 ring-white/25 transition duration-300 group-hover:scale-110 group-hover:bg-gold-300 motion-reduce:transition-none">
                    <svg viewBox="0 0 24 24" class="h-1/2 w-1/2 translate-x-[6%]" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.52.85l10.6-6.86a1 1 0 0 0 0-1.7L9.52 4.29A1 1 0 0 0 8 5.14Z"/></svg>
                </span>
            </span>
        </button>
    </div>

    @once
        <script>
            document.addEventListener('click', function (event) {
                var trigger = event.target.closest('[data-youtube-facade]');
                if (! trigger) { return; }
                var iframe = document.createElement('iframe');
                iframe.src = trigger.getAttribute('data-embed');
                iframe.title = trigger.getAttribute('data-title') || 'YouTube video';
                iframe.className = 'absolute inset-0 h-full w-full';
                iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
                iframe.setAttribute('allowfullscreen', '');
                iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
                iframe.style.border = '0';
                trigger.replaceWith(iframe);
                iframe.focus();
            });
        </script>
    @endonce
@endif
