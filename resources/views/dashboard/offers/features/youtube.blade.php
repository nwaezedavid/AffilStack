@php($hasYoutubeScript = $generations->where('module', 'youtube_script')->where('status', 'completed')->isNotEmpty())
<div class="rounded-3xl border border-line bg-white p-5 sm:p-6">
    <h3 class="font-display text-lg font-semibold text-navy-900">Two steps to a ranking video</h3>
    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        @foreach ([
            [route('offers.youtube.script', $offer), 'Step 1 · Video script', 'Hook, talking points, demo beats and call to action, plus B-roll ideas.', config('credits.costs.youtube_script'), 'document-text'],
            [route('offers.youtube.metadata', $offer), 'Step 2 · Title, description & tags', 'Click-worthy titles, an SEO description with your link, keywords and tags.', config('credits.costs.youtube_metadata'), 'tag', $hasYoutubeScript ? null : 'Generate the video script first — the metadata is written to match it.'],
        ] as $action)
            @include('dashboard.offers.features._action')
        @endforeach
    </div>
</div>
