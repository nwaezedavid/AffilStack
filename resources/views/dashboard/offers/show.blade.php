@extends('layouts.app')

@section('title', $offer->product_name)

@push('head')
    @if ($offer->status === 'queued' || $offer->generations->where('status', 'queued')->isNotEmpty())
        <meta http-equiv="refresh" content="8">
    @endif
@endpush

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-3 flex-wrap">
            <h2 class="font-display font-semibold text-xl text-navy-900">{{ $offer->product_name }}</h2>
            <span class="text-xs font-mono px-2 py-1 rounded bg-surface-muted text-ink-600 capitalize">{{ $offer->status }}</span>
            @if ($offer->status === 'queued')
                <span class="text-xs text-ink-400">Running in the background — you'll be notified when it's ready.</span>
            @endif
        </div>
        <div class="text-sm text-ink-600 mt-1">
            <a href="{{ $offer->product_url }}" target="_blank" rel="noopener" class="text-brand-600 hover:text-brand-700">{{ $offer->product_url }}</a>
            · {{ $offer->affiliate_network }}
        </div>
    </div>

    @if ($offer->status === 'ready')
        {{-- Research summary --}}
        <div class="bg-surface border border-line rounded-lg p-6 mb-8">
            <h3 class="font-display font-semibold text-sm text-navy-900 mb-3">Who to sell to & how</h3>
            <dl class="space-y-4 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Ideal customer</dt>
                    <dd class="text-ink-900">{{ $offer->ideal_customer_summary }}</dd>
                </div>
                @if (!empty($offer->research_data['pain_points']))
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Pain points</dt>
                        <dd class="text-ink-900">
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach ($offer->research_data['pain_points'] as $pain)
                                    <li>{{ $pain }}</li>
                                @endforeach
                            </ul>
                        </dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Where to find them</dt>
                    <dd class="text-ink-900 whitespace-pre-line">{{ $offer->where_to_find }}</dd>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Recommended channel</dt>
                        <dd class="text-ink-900 capitalize font-medium">{{ str_replace('_', ' ', $offer->recommended_channel) }}</dd>
                        @if (!empty($offer->research_data['recommended_channel_reason']))
                            <dd class="text-ink-600 text-xs mt-1">{{ $offer->research_data['recommended_channel_reason'] }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Angle to use</dt>
                        <dd class="text-ink-900">{{ $offer->recommended_angle }}</dd>
                    </div>
                </div>
            </dl>
        </div>

        {{-- Generate content --}}
        <div class="grid sm:grid-cols-2 gap-4 mb-8">
            <div class="bg-surface border border-line rounded-lg p-5">
                <h3 class="font-display font-semibold text-sm text-navy-900 mb-1">Blog / Medium article</h3>
                <p class="text-xs text-ink-600 mb-3">SEO-optimized, formatted to convert. <span class="font-mono text-ink-400">({{ config('credits.costs.blog_article') }} credits)</span></p>
                <form method="POST" action="{{ route('offers.blog.store', $offer) }}" class="flex gap-2">
                    @csrf
                    <input name="target_keyword" placeholder="Target keyword (optional)" class="flex-1 rounded-md border border-line px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <button class="rounded-md bg-navy-900 text-white text-sm px-3 py-1.5 hover:bg-navy-800 transition whitespace-nowrap">Generate</button>
                </form>
            </div>

            <div class="bg-surface border border-line rounded-lg p-5">
                <h3 class="font-display font-semibold text-sm text-navy-900 mb-1">LinkedIn</h3>
                <p class="text-xs text-ink-600 mb-3">Content only — you send it. AffiliStack never DMs or posts for you.</p>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('offers.linkedin.keywords', $offer) }}">
                        @csrf
                        <button class="rounded-md border border-line text-ink-900 text-xs px-2.5 py-1.5 hover:bg-surface-muted transition">Keywords ({{ config('credits.costs.linkedin_keywords') }})</button>
                    </form>
                    <form method="POST" action="{{ route('offers.linkedin.dm', $offer) }}">
                        @csrf
                        <button class="rounded-md border border-line text-ink-900 text-xs px-2.5 py-1.5 hover:bg-surface-muted transition">DM sequence ({{ config('credits.costs.linkedin_dm_sequence') }})</button>
                    </form>
                    <form method="POST" action="{{ route('offers.linkedin.post', $offer) }}">
                        @csrf
                        <button class="rounded-md border border-line text-ink-900 text-xs px-2.5 py-1.5 hover:bg-surface-muted transition">Post ({{ config('credits.costs.linkedin_post') }})</button>
                    </form>
                    <form method="POST" action="{{ route('offers.linkedin.article', $offer) }}">
                        @csrf
                        <button class="rounded-md border border-line text-ink-900 text-xs px-2.5 py-1.5 hover:bg-surface-muted transition">Article ({{ config('credits.costs.linkedin_article') }})</button>
                    </form>
                </div>
            </div>

            <div class="bg-surface border border-line rounded-lg p-5">
                <h3 class="font-display font-semibold text-sm text-navy-900 mb-1">YouTube</h3>
                @if (auth()->user()->canUseChannel('youtube'))
                    @php
                        $hasYoutubeScript = $offer->generations->where('module', 'youtube_script')->where('status', 'completed')->isNotEmpty();
                    @endphp
                    <p class="text-xs text-ink-600 mb-3">Script first, then metadata written to match it.</p>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('offers.youtube.script', $offer) }}">
                            @csrf
                            <button class="rounded-md border border-line text-ink-900 text-xs px-2.5 py-1.5 hover:bg-surface-muted transition">Video script ({{ config('credits.costs.youtube_script') }})</button>
                        </form>
                        <form method="POST" action="{{ route('offers.youtube.metadata', $offer) }}">
                            @csrf
                            <button
                                @if (! $hasYoutubeScript) disabled title="Generate the video script first" @endif
                                class="rounded-md border border-line text-ink-900 text-xs px-2.5 py-1.5 hover:bg-surface-muted transition disabled:opacity-40 disabled:cursor-not-allowed"
                            >Title, description &amp; tags ({{ config('credits.costs.youtube_metadata') }})</button>
                        </form>
                    </div>
                @else
                    <p class="text-xs text-ink-600 mb-3">Not included in your current plan.</p>
                    <a href="{{ route('billing.index') }}" class="text-xs text-brand-600 hover:text-brand-700 underline">Upgrade to unlock the YouTube module &rarr;</a>
                @endif
            </div>
        </div>
    @elseif ($offer->status === 'queued')
        <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600 mb-8">
            Research is running in the background — this page refreshes automatically, or check the bell icon for a notification.
        </div>
    @else
        <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600 mb-8">
            Research failed — try submitting again from the offers list.
        </div>
    @endif

    {{-- Generated content history --}}
    @if ($offer->generations->where('module', '!=', 'research')->isNotEmpty())
        <h3 class="font-display font-semibold text-sm text-navy-900 mb-3">Generated content</h3>
        <div class="space-y-4">
            @foreach ($offer->generations->where('module', '!=', 'research')->sortByDesc('created_at') as $gen)
                <div class="bg-surface border border-line rounded-lg p-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-mono uppercase tracking-wide text-brand-600">{{ str_replace('_', ' ', $gen->module) }}</span>
                        <span class="text-xs text-ink-400">{{ $gen->created_at->diffForHumans() }}</span>
                    </div>

                    @if ($gen->status === 'queued')
                        <p class="text-sm text-ink-600">Running in the background — this page refreshes automatically.</p>
                    @elseif ($gen->status === 'failed')
                        <p class="text-sm text-red-700">Generation failed: {{ $gen->error_message }}</p>
                    @elseif ($gen->module === 'blog_article')
                        <p class="font-medium text-ink-900 mb-1">{{ $gen->output_meta['title'] ?? '' }}</p>
                        <p class="text-xs text-ink-600 mb-3">{{ $gen->output_meta['meta_description'] ?? '' }}</p>
                        <details>
                            <summary class="cursor-pointer text-sm text-brand-600 hover:text-brand-700">View full article</summary>
                            <pre class="whitespace-pre-wrap text-sm text-ink-900 mt-3 font-sans">{{ $gen->output }}</pre>
                        </details>
                    @elseif ($gen->module === 'linkedin_keywords')
                        <div class="grid sm:grid-cols-2 gap-3 text-sm">
                            <div>
                                <div class="text-xs uppercase text-ink-400 font-mono mb-1">Search keywords</div>
                                <div class="text-ink-900">{{ implode(', ', $gen->output_meta['search_keywords'] ?? []) }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase text-ink-400 font-mono mb-1">Job titles</div>
                                <div class="text-ink-900">{{ implode(', ', $gen->output_meta['job_titles'] ?? []) }}</div>
                            </div>
                            <div class="sm:col-span-2">
                                <div class="text-xs uppercase text-ink-400 font-mono mb-1">Boolean search strings</div>
                                @foreach ($gen->output_meta['boolean_search_strings'] ?? [] as $bs)
                                    <code class="block bg-surface-muted rounded px-2 py-1 text-xs mb-1">{{ $bs }}</code>
                                @endforeach
                            </div>
                        </div>
                    @elseif ($gen->module === 'linkedin_dm_sequence')
                        <div class="space-y-3">
                            @foreach ($gen->output_meta['messages'] ?? [] as $msg)
                                <div class="border-l-2 border-gold-500 pl-3">
                                    <div class="text-xs text-ink-400 font-mono">Step {{ $msg['step'] ?? '' }} · {{ $msg['send_timing'] ?? '' }}</div>
                                    <div class="text-sm text-ink-900 whitespace-pre-line">{{ $msg['message'] ?? '' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @elseif ($gen->module === 'linkedin_post')
                        <p class="text-sm text-ink-900 whitespace-pre-line mb-3">{{ $gen->output_meta['post_text'] ?? '' }}</p>
                        <div class="text-xs text-ink-600"><span class="font-mono uppercase text-ink-400">Image prompt: </span>{{ $gen->output_meta['image_prompt'] ?? '' }}</div>
                        <div class="text-xs text-ink-600"><span class="font-mono uppercase text-ink-400">Best time: </span>{{ $gen->output_meta['best_posting_time'] ?? '' }}</div>
                    @elseif ($gen->module === 'linkedin_article')
                        <p class="font-medium text-ink-900 mb-2">{{ $gen->output_meta['headline'] ?? '' }}</p>
                        <details>
                            <summary class="cursor-pointer text-sm text-brand-600 hover:text-brand-700">View full article</summary>
                            <pre class="whitespace-pre-wrap text-sm text-ink-900 mt-3 font-sans">{{ $gen->output_meta['article_markdown'] ?? '' }}</pre>
                        </details>
                    @elseif ($gen->module === 'youtube_script')
                        <p class="font-medium text-ink-900 mb-1">{{ $gen->output_meta['working_title'] ?? '' }}</p>
                        <p class="text-xs text-ink-600 mb-3">~{{ $gen->output_meta['estimated_length_minutes'] ?? '?' }} min · {{ $gen->output_meta['hook'] ?? '' }}</p>
                        <details>
                            <summary class="cursor-pointer text-sm text-brand-600 hover:text-brand-700">View full script</summary>
                            <pre class="whitespace-pre-wrap text-sm text-ink-900 mt-3 font-sans">{{ $gen->output_meta['script_markdown'] ?? '' }}</pre>
                            @if (!empty($gen->output_meta['b_roll_suggestions']))
                                <div class="text-xs uppercase text-ink-400 font-mono mt-3 mb-1">B-roll ideas</div>
                                <ul class="list-disc list-inside text-sm text-ink-900 space-y-0.5">
                                    @foreach ($gen->output_meta['b_roll_suggestions'] as $idea)
                                        <li>{{ $idea }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </details>
                    @elseif ($gen->module === 'youtube_metadata')
                        <div class="text-xs uppercase text-ink-400 font-mono mb-1">Title options</div>
                        <ul class="list-disc list-inside text-sm text-ink-900 mb-3 space-y-0.5">
                            @foreach ($gen->output_meta['title_options'] ?? [] as $title)
                                <li>{{ $title }}</li>
                            @endforeach
                        </ul>
                        <details class="mb-3">
                            <summary class="cursor-pointer text-sm text-brand-600 hover:text-brand-700">View description</summary>
                            <pre class="whitespace-pre-wrap text-sm text-ink-900 mt-3 font-sans">{{ $gen->output_meta['description'] ?? '' }}</pre>
                        </details>
                        <div class="grid sm:grid-cols-2 gap-3 text-sm mb-3">
                            <div>
                                <div class="text-xs uppercase text-ink-400 font-mono mb-1">Keywords</div>
                                <div class="text-ink-900">{{ implode(', ', $gen->output_meta['keywords'] ?? []) }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase text-ink-400 font-mono mb-1">Tags</div>
                                <div class="text-ink-900">{{ implode(', ', $gen->output_meta['tags'] ?? []) }}</div>
                            </div>
                        </div>
                        <div class="text-xs text-ink-600"><span class="font-mono uppercase text-ink-400">Category: </span>{{ $gen->output_meta['category'] ?? '' }}</div>
                        <div class="text-xs text-ink-600"><span class="font-mono uppercase text-ink-400">Thumbnail prompt: </span>{{ $gen->output_meta['thumbnail_prompt'] ?? '' }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection
