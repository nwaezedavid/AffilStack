<div class="rounded-3xl border border-line bg-white p-5 sm:p-6">
    <h3 class="font-display text-lg font-semibold text-navy-900">Choose what to create</h3>
    <p class="mt-1 text-sm text-ink-600">Start with keywords to find buyers, then a DM sequence to talk to them. Posts and articles make you look like the expert when they check your profile. You send everything yourself — AffilStack never messages anyone for you.</p>
    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        @foreach ([
            [route('offers.linkedin.keywords', $offer), '1. Search keywords', 'Keywords, job titles and Boolean strings to find buyers in LinkedIn search.', config('credits.costs.linkedin_keywords'), 'magnifying-glass'],
            [route('offers.linkedin.dm', $offer), '2. DM sequence', 'A soft connection note plus follow-ups that start conversations, not pitches.', config('credits.costs.linkedin_dm_sequence'), 'chat-bubble-left-right'],
            [route('offers.linkedin.post', $offer), '3. Post', 'A scroll-stopping post with an image idea and the best time to publish.', config('credits.costs.linkedin_post'), 'megaphone'],
            [route('offers.linkedin.article', $offer), '4. Article', 'A long-form LinkedIn article that builds authority and links to the offer.', config('credits.costs.linkedin_article'), 'newspaper'],
        ] as $action)
            @include('dashboard.offers.features._action')
        @endforeach
    </div>
    <a href="{{ route('offers.linkedin.reply-assistant', $offer) }}" class="mt-3 flex items-center justify-between gap-3 rounded-2xl bg-gradient-to-r from-sky-50 to-white p-4 ring-1 ring-sky-100 transition hover:ring-sky-300">
        <span class="flex items-start gap-3">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white ring-1 ring-sky-100"><x-ui.brand-icon name="linkedin" class="h-5 w-5" /></span>
            <span>
                <span class="block font-semibold text-navy-900">Someone replied? Use the reply assistant</span>
                <span class="block text-sm text-ink-600">Paste their message and get the perfect answer ({{ config('credits.costs.linkedin_reply_draft') }} credits). The Chrome extension does this right inside LinkedIn.</span>
            </span>
        </span>
        <x-heroicon-m-arrow-right class="h-5 w-5 shrink-0 text-sky-600" />
    </a>
</div>
@include('dashboard.offers.partials.linkedin-keywords')
