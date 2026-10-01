<div class="rounded-3xl border border-line bg-white p-5 sm:p-6">
    <h3 class="font-display text-lg font-semibold text-navy-900">Pick a piece to localize</h3>
    @if ($localizable->isEmpty())
        <p class="mt-1 text-sm text-ink-600">You haven't generated any content for {{ $offer->product_name }} yet. Create a blog article, LinkedIn post, X thread or TikTok package first — then come back to adapt it for another market.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <x-ui.button :href="route('offers.feature', [$offer, 'blog'])" variant="primary" size="sm" icon="document-text">Write a blog article</x-ui.button>
            <x-ui.button :href="route('offers.show', $offer)" variant="secondary" size="sm">See all tools</x-ui.button>
        </div>
    @else
        <p class="mt-1 text-sm text-ink-600">Each copy is rewritten for the market — spelling, currency, cultural references and disclosure wording.</p>
        <ul class="mt-4 space-y-2.5">
            @foreach ($localizable as $source)
                <li class="flex flex-col gap-3 rounded-2xl bg-surface-muted p-3.5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-violet-600">{{ \Illuminate\Support\Str::headline($source->module) }}</p>
                        <p class="truncate text-sm font-medium text-navy-900">{{ $source->calendarTitle() }}</p>
                        <p class="text-xs text-ink-400">{{ $source->created_at->diffForHumans() }}{{ $source->localizations()->count() ? ' · '.$source->localizations()->count().' localized' : '' }}</p>
                    </div>
                    <form method="POST" action="{{ route('generations.localize', $source) }}" class="flex flex-wrap items-center gap-2">
                        @csrf
                        <label for="market-{{ $source->id }}" class="sr-only">Market</label>
                        <select id="market-{{ $source->id }}" name="target_market" required class="rounded-full border border-line bg-white px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="">Choose a market…</option>
                            @foreach (config('localization.markets') as $code => $market)
                                <option value="{{ $code }}">{{ $market['flag'] }} {{ $market['label'] }}</option>
                            @endforeach
                        </select>
                        <x-ui.button variant="primary" size="sm" icon="globe-europe-africa">Localize ({{ config('credits.costs.localization') }})</x-ui.button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</div>
