@extends('layouts.partner')

@section('title', 'Banners & ads')

@section('content')
    <section class="mb-8 overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-white" aria-labelledby="slot-heading">
        <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <div>
                <p class="inline-flex items-center gap-1.5 rounded-full bg-emerald-600 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-white"><x-heroicon-s-arrow-path class="h-3.5 w-3.5" /> Set it once</p>
                <h2 id="slot-heading" class="mt-3 font-display text-xl font-semibold text-navy-900">Auto-updating ad slot</h2>
                <p class="mt-1.5 text-sm text-ink-600">Paste one line of code where you want the ad. It always shows AffilStack's current ads: when we change a headline, launch a discount or swap an image, your site updates by itself. Every view and click is credited to you.</p>
                <p class="mt-2 text-xs text-ink-400">
                    {{ $liveAds > 0 ? $liveAds.' '.\Illuminate\Support\Str::plural('ad', $liveAds).' live right now.' : 'No promotion is running right now, so the slot shows our standard banner designs until the next one starts.' }}
                </p>

                <div class="mt-5">
                    <label for="slot-size" class="mb-1 block text-xs font-medium text-ink-900">Size</label>
                    <select id="slot-size" class="w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600">
                        @foreach ($slots as $size => $slot)
                            <option value="{{ $size }}" @selected($size === '300x250')>{{ str_replace('x', '×', $size) }} · {{ $slot['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <label for="slot-code" class="mt-4 mb-1 block text-xs font-medium text-ink-900">Your code</label>
                <textarea id="slot-code" readonly rows="3" onclick="this.select()" class="w-full rounded-xl border border-line bg-navy-950 p-3 font-mono text-xs leading-relaxed text-emerald-200 focus:outline-none focus:ring-2 focus:ring-emerald-600">{{ $slots['300x250']['script'] ?? '' }}</textarea>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <x-ui.copy-button target="slot-code" label="Copy code" variant="primary" size="md" />
                    <span class="text-xs text-ink-400">Works on WordPress (Custom HTML block), Blogger, Webflow and any HTML page.</span>
                </div>
            </div>
            <div class="flex min-h-64 items-center justify-center rounded-xl bg-[radial-gradient(circle_at_1px_1px,#d6e9df_1px,transparent_0)] [background-size:14px_14px] bg-white/60 p-4 ring-1 ring-emerald-100">
                <div id="slot-preview-wrap" class="overflow-hidden rounded-md shadow-lg">
                    <iframe id="slot-preview" title="Live preview of your ad slot" scrolling="no" tabindex="-1" sandbox="allow-popups allow-popups-to-escape-sandbox" class="pointer-events-none origin-top-left border-0"></iframe>
                </div>
            </div>
        </div>
    </section>

    <h2 class="mb-4 font-display text-lg font-semibold text-navy-900">All banners</h2>
    @include('dashboard.referrals.partials.banners')
@endsection

@push('scripts')
    <script>
        (function () {
            var slots = @json($slots);
            var select = document.getElementById('slot-size');
            var code = document.getElementById('slot-code');
            var frame = document.getElementById('slot-preview');
            var wrap = document.getElementById('slot-preview-wrap');

            function show(size) {
                var slot = slots[size];
                if (!slot) { return; }
                var scale = Math.min(1, (wrap.parentElement.clientWidth - 32) / slot.width, 320 / slot.height);
                code.value = slot.script;
                frame.width = slot.width;
                frame.height = slot.height;
                frame.style.transform = 'scale(' + scale + ')';
                wrap.style.width = Math.round(slot.width * scale) + 'px';
                wrap.style.height = Math.round(slot.height * scale) + 'px';
                frame.src = slot.preview;
            }

            select.addEventListener('change', function () { show(select.value); });
            show(select.value);
        })();
    </script>
@endpush
