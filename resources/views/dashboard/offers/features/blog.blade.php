<form method="POST" action="{{ route('offers.blog.store', $offer) }}" class="rounded-3xl border border-line bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04)] sm:p-6">
    @csrf
    <h3 class="font-display text-lg font-semibold text-navy-900">Write a new article</h3>
    <p class="mt-1 text-sm text-ink-600">Type the Google search you want this article to rank for — or leave it blank and the AI picks the best buyer-intent keyword for {{ $offer->product_name }}.</p>
    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
        <div class="flex-1">
            <label for="target_keyword" class="sr-only">Target keyword</label>
            <input id="target_keyword" name="target_keyword" maxlength="255" value="{{ old('target_keyword') }}"
                   placeholder="e.g. best {{ \Illuminate\Support\Str::lower($offer->product_name) }} alternative, {{ \Illuminate\Support\Str::lower($offer->product_name) }} review"
                   class="w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <x-ui.button variant="primary" icon="sparkles" size="lg">Write article ({{ config('credits.costs.blog_article') }} credits)</x-ui.button>
    </div>
    <p class="mt-3 flex items-center gap-1.5 text-xs text-ink-400"><x-heroicon-m-shield-check class="h-4 w-4 text-emerald-500" /> Your cloaked link and the right affiliate disclosure are inserted automatically.</p>
</form>
