<div class="rounded-3xl border border-line bg-white p-5 sm:p-6">
    <div class="grid gap-3 sm:grid-cols-2">
        @include('dashboard.offers.features._action', ['action' => [route('offers.competitor.scan', $offer), 'Scan competitor angles', 'The angles other affiliates already use for this product, how crowded each is, and a fresh one to own.', config('credits.costs.competitor_angles'), 'eye']])
        <div class="rounded-2xl bg-amber-50/70 p-4 text-sm text-ink-600 ring-1 ring-amber-100">
            <p class="font-semibold text-navy-900">Read the saturation colours</p>
            <ul class="mt-2 space-y-1.5">
                <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span> High — everyone says this; avoid.</li>
                <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Medium — use with a twist.</li>
                <li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Low — open space, go for it.</li>
            </ul>
        </div>
    </div>
</div>
