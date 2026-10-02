<div class="rounded-3xl border border-line bg-white p-5 sm:p-6">
    <div class="grid gap-3 sm:grid-cols-2">
        @include('dashboard.offers.features._action', ['action' => [route('offers.tiktok.video', $offer), 'Generate a video package', 'A 30–60 second script, timed on-screen text and a caption with hashtags.', config('credits.costs.tiktok_video'), 'musical-note']])
        <a href="{{ route('offers.feature', [$offer, 'ugc']) }}" class="flex flex-col justify-between rounded-2xl bg-gradient-to-br from-rose-50 to-white p-4 ring-1 ring-rose-100 transition hover:ring-rose-300">
            <span>
                <span class="flex items-center gap-2 font-semibold text-navy-900"><x-heroicon-o-video-camera class="h-5 w-5 text-rose-600" /> Camera-shy?</span>
                <span class="mt-1 block text-sm text-ink-600">Let an AI presenter read a script for you and get a finished vertical video.</span>
            </span>
            <span class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-rose-600">Open UGC video <x-heroicon-m-arrow-right class="h-4 w-4" /></span>
        </a>
    </div>
</div>
