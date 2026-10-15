@props(['src', 'alt' => '', 'eager' => false])

{{-- A built-in feature screenshot, or its animated demo when Admin →
     Website → Feature demos says so (App\Support\Features\FeatureMedia).
     The video is silent, loops, plays only while on screen, and keeps the
     screenshot as its poster; visitors who prefer reduced motion keep
     seeing the screenshot. --}}
@php($sources = \App\Support\Features\FeatureMedia::videoFor($src))
@if ($sources)
    <video {{ $attributes }} poster="{{ $src }}" muted loop playsinline disablepictureinpicture preload="none" @if ($alt !== '') aria-label="{{ $alt }}" @else aria-hidden="true" @endif data-feature-animation>
        @foreach ($sources as $source)
            <source src="{{ $source['url'] }}" type="{{ $source['type'] }}">
        @endforeach
    </video>
    @once
        <script>
            (() => {
                const start = () => {
                    const videos = document.querySelectorAll('video[data-feature-animation]');
                    if (! videos.length || window.matchMedia('(prefers-reduced-motion: reduce)').matches || ! ('IntersectionObserver' in window)) {
                        return;
                    }
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(({ target, isIntersecting }) => {
                            if (isIntersecting) {
                                target.play().catch(() => {});
                            } else {
                                target.pause();
                            }
                        });
                    }, { threshold: 0.25 });
                    videos.forEach((video) => observer.observe(video));
                };
                document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
            })();
        </script>
    @endonce
@else
    <img src="{{ $src }}" alt="{{ $alt }}" {{ $attributes }} @if ($eager) fetchpriority="high" @else loading="lazy" @endif decoding="async">
@endif
