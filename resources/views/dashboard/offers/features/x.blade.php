<div class="rounded-3xl border border-line bg-white p-5 sm:p-6">
    <div class="grid gap-3 sm:grid-cols-2">
        @include('dashboard.offers.features._action', ['action' => [route('offers.x.thread', $offer), 'Generate a thread', 'A 6–10 post thread that teaches first and sells second, plus alternative hooks to test.', config('credits.costs.x_thread'), 'chat-bubble-bottom-center-text']])
        <div class="rounded-2xl bg-slate-50 p-4 text-sm text-ink-600 ring-1 ring-slate-200">
            <p class="font-semibold text-navy-900">Tips for threads that convert</p>
            <ul class="mt-2 space-y-1.5">
                <li class="flex gap-2"><x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" /> Post when your audience is online (see "best time").</li>
                <li class="flex gap-2"><x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" /> Reply to every comment in the first hour.</li>
                <li class="flex gap-2"><x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" /> Pin your best thread to your profile.</li>
            </ul>
        </div>
    </div>
</div>
