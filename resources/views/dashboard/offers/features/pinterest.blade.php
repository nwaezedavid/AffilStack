<div class="rounded-3xl border border-line bg-white p-5 sm:p-6">
    <div class="grid gap-3 sm:grid-cols-2">
        @include('dashboard.offers.features._action', ['action' => [route('offers.pinterest.pins', $offer), 'Generate 3 pins', 'Titles, keyword-rich descriptions, image prompts and alt text — plus the board to use.', config('credits.costs.pinterest_pin'), 'photo']])
        <div class="rounded-2xl bg-red-50/60 p-4 text-sm text-ink-600 ring-1 ring-red-100">
            <p class="flex items-center gap-2 font-semibold text-navy-900"><x-ui.brand-icon name="pinterest" class="h-4 w-4" /> Why Pinterest works</p>
            <p class="mt-1">Pins keep getting clicks for months. Make all three, keep the one that gets the most saves, and make more like it.</p>
        </div>
    </div>
</div>
