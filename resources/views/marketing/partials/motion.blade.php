{{-- Shared look-and-feel helpers for marketing pages beyond the homepage:
     the hero grid pattern and the scroll-reveal animation (same values as
     marketing/home.blade.php). --}}
@push('head')
    <style>
        .home-grid-pattern { background-image: linear-gradient(rgba(255,255,255,.06) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.06) 1px, transparent 1px); background-size: 44px 44px; mask-image: radial-gradient(ellipse at 30% 20%, #000 20%, transparent 70%); }
        .js-reveal [data-reveal] { opacity: 0; transform: translateY(18px); transition: opacity .7s ease, transform .7s cubic-bezier(.2,.7,.2,1); }
        .js-reveal [data-reveal].is-visible { opacity: 1; transform: none; }
        html { scroll-padding-top: 5rem; scroll-behavior: smooth; }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .js-reveal [data-reveal] { opacity: 1; transform: none; transition: none; }
        }
    </style>
    <script>
        if ('IntersectionObserver' in window && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.classList.add('js-reveal');
            document.addEventListener('DOMContentLoaded', function () {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); }
                    });
                }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
                document.querySelectorAll('[data-reveal]').forEach(function (el) { observer.observe(el); });
            });
        }
    </script>
@endpush
