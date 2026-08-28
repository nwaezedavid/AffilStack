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

    {{-- Disclosure jurisdiction — governs which affiliate-disclosure wording
         is auto-inserted into this offer's generated content below. --}}
    <div class="bg-surface border border-line rounded-lg p-4 mb-6 flex items-center gap-3 flex-wrap">
        <span class="text-xs font-mono uppercase tracking-wide text-ink-400">Disclosure jurisdiction</span>
        <form method="POST" action="{{ route('offers.disclosure.update', $offer) }}" class="flex items-center gap-2">
            @csrf
            @method('PATCH')
            <select name="disclosure_country" onchange="this.form.submit()" class="rounded-md border border-line px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                @foreach (app(\App\Services\Compliance\DisclosureService::class)->countries() as $code => $label)
                    <option value="{{ $code }}" @selected($offer->disclosure_country === $code)>{{ $label }}</option>
                @endforeach
            </select>
            <noscript><button class="rounded-md border border-line text-xs px-2.5 py-1.5 hover:bg-surface-muted transition">Save</button></noscript>
        </form>
        <span class="text-xs text-ink-400">Content below auto-includes the right disclosure wording for this audience. Not legal advice — review before publishing.</span>
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

            <div class="bg-surface border border-line rounded-lg p-5">
                <h3 class="font-display font-semibold text-sm text-navy-900 mb-1">UGC</h3>
                @if (auth()->user()->canUseChannel('ugc'))
                    <p class="text-xs text-ink-600 mb-3">Get angle ideas, pick one below, then get a script plus a per-platform posting pack.</p>
                    <form method="POST" action="{{ route('offers.ugc.angles', $offer) }}">
                        @csrf
                        <button class="rounded-md border border-line text-ink-900 text-xs px-2.5 py-1.5 hover:bg-surface-muted transition">Suggest UGC angles ({{ config('credits.costs.ugc_angles') }})</button>
                    </form>
                @else
                    <p class="text-xs text-ink-600 mb-3">Not included in your current plan.</p>
                    <a href="{{ route('billing.index') }}" class="text-xs text-brand-600 hover:text-brand-700 underline">Upgrade to unlock the UGC module &rarr;</a>
                @endif
            </div>

            <div class="bg-surface border border-line rounded-lg p-5">
                <h3 class="font-display font-semibold text-sm text-navy-900 mb-1">X (Twitter)</h3>
                @if (auth()->user()->canUseChannel('x'))
                    <p class="text-xs text-ink-600 mb-3">A full thread plus alternative opening hooks to test.</p>
                    <form method="POST" action="{{ route('offers.x.thread', $offer) }}">
                        @csrf
                        <button class="rounded-md border border-line text-ink-900 text-xs px-2.5 py-1.5 hover:bg-surface-muted transition">Generate thread ({{ config('credits.costs.x_thread') }})</button>
                    </form>
                @else
                    <p class="text-xs text-ink-600 mb-3">Not included in your current plan.</p>
                    <a href="{{ route('billing.index') }}" class="text-xs text-brand-600 hover:text-brand-700 underline">Upgrade to unlock the X module &rarr;</a>
                @endif
            </div>

            <div class="bg-surface border border-line rounded-lg p-5">
                <h3 class="font-display font-semibold text-sm text-navy-900 mb-1">TikTok</h3>
                @if (auth()->user()->canUseChannel('tiktok'))
                    <p class="text-xs text-ink-600 mb-3">Script, on-screen text cues, and caption in one pass.</p>
                    <form method="POST" action="{{ route('offers.tiktok.video', $offer) }}">
                        @csrf
                        <button class="rounded-md border border-line text-ink-900 text-xs px-2.5 py-1.5 hover:bg-surface-muted transition">Generate video package ({{ config('credits.costs.tiktok_video') }})</button>
                    </form>
                @else
                    <p class="text-xs text-ink-600 mb-3">Not included in your current plan.</p>
                    <a href="{{ route('billing.index') }}" class="text-xs text-brand-600 hover:text-brand-700 underline">Upgrade to unlock the TikTok module &rarr;</a>
                @endif
            </div>

            <div class="bg-surface border border-line rounded-lg p-5">
                <h3 class="font-display font-semibold text-sm text-navy-900 mb-1">Email nurture</h3>
                @if ($contacts->isEmpty())
                    <p class="text-xs text-ink-600 mb-3">A 5-email sequence personalized to a real contact — introduces this offer without a hard pitch.</p>
                    <a href="{{ route('crm.index') }}" class="text-xs text-brand-600 hover:text-brand-700 underline">Add a CRM contact first &rarr;</a>
                @else
                    <p class="text-xs text-ink-600 mb-3">Pick who to nurture — the sequence is written for them specifically, not a generic template.</p>
                    <form method="POST" action="{{ route('offers.nurture.generate', $offer) }}" class="flex gap-2">
                        @csrf
                        <select name="contact_id" required class="flex-1 rounded-md border border-line px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="">Choose a contact&hellip;</option>
                            @foreach ($contacts as $contact)
                                <option value="{{ $contact->id }}">{{ $contact->name ?: $contact->email ?: 'Contact #'.$contact->id }}{{ $contact->company ? ' · '.$contact->company : '' }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-md bg-navy-900 text-white text-sm px-3 py-1.5 hover:bg-navy-800 transition whitespace-nowrap">Generate ({{ config('credits.costs.email_nurture') }})</button>
                    </form>
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
                        <div class="mb-2">@include('dashboard.offers._disclosure_badge', ['text' => $gen->output])</div>
                        <details>
                            <summary class="cursor-pointer text-sm text-brand-600 hover:text-brand-700">View full article</summary>
                            <pre class="whitespace-pre-wrap text-sm text-ink-900 mt-3 font-sans">{{ $offer->cloak($gen->output, $gen->module) }}</pre>
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
                        <div class="mb-2">@include('dashboard.offers._disclosure_badge', ['text' => $gen->output_meta['post_text'] ?? ''])</div>
                        <p class="text-sm text-ink-900 whitespace-pre-line mb-3">{{ $offer->cloak($gen->output_meta['post_text'] ?? '', $gen->module) }}</p>
                        <div class="text-xs text-ink-600"><span class="font-mono uppercase text-ink-400">Image prompt: </span>{{ $gen->output_meta['image_prompt'] ?? '' }}</div>
                        <div class="text-xs text-ink-600"><span class="font-mono uppercase text-ink-400">Best time: </span>{{ $gen->output_meta['best_posting_time'] ?? '' }}</div>
                    @elseif ($gen->module === 'linkedin_article')
                        <p class="font-medium text-ink-900 mb-2">{{ $gen->output_meta['headline'] ?? '' }}</p>
                        <div class="mb-2">@include('dashboard.offers._disclosure_badge', ['text' => $gen->output_meta['article_markdown'] ?? ''])</div>
                        <details>
                            <summary class="cursor-pointer text-sm text-brand-600 hover:text-brand-700">View full article</summary>
                            <pre class="whitespace-pre-wrap text-sm text-ink-900 mt-3 font-sans">{{ $offer->cloak($gen->output_meta['article_markdown'] ?? '', $gen->module) }}</pre>
                        </details>
                    @elseif ($gen->module === 'youtube_script')
                        <p class="font-medium text-ink-900 mb-1">{{ $gen->output_meta['working_title'] ?? '' }}</p>
                        <p class="text-xs text-ink-600 mb-3">~{{ $gen->output_meta['estimated_length_minutes'] ?? '?' }} min · {{ $gen->output_meta['hook'] ?? '' }}</p>
                        <details>
                            <summary class="cursor-pointer text-sm text-brand-600 hover:text-brand-700">View full script</summary>
                            <pre class="whitespace-pre-wrap text-sm text-ink-900 mt-3 font-sans">{{ $offer->cloak($gen->output_meta['script_markdown'] ?? '', $gen->module, withDisclosure: false) }}</pre>
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
                        <div class="mb-2">@include('dashboard.offers._disclosure_badge', ['text' => $gen->output_meta['description'] ?? ''])</div>
                        <details class="mb-3">
                            <summary class="cursor-pointer text-sm text-brand-600 hover:text-brand-700">View description</summary>
                            <pre class="whitespace-pre-wrap text-sm text-ink-900 mt-3 font-sans">{{ $offer->cloak($gen->output_meta['description'] ?? '', $gen->module) }}</pre>
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
                    @elseif ($gen->module === 'ugc_angles')
                        <div class="space-y-3">
                            @foreach ($gen->output_meta['angles'] ?? [] as $index => $angle)
                                @php
                                    $alreadyGenerated = $offer->generations->contains(fn ($g) => $g->module === 'ugc_content'
                                        && ($g->input['angles_generation_id'] ?? null) === $gen->id
                                        && ($g->input['angle_index'] ?? null) === $index);
                                @endphp
                                <div class="border-l-2 border-gold-500 pl-3 flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-medium text-ink-900">{{ $angle['name'] ?? '' }} <span class="text-xs font-mono text-ink-400">&middot; {{ $angle['format'] ?? '' }}</span></div>
                                        <div class="text-sm text-ink-900">{{ $angle['hook_idea'] ?? '' }}</div>
                                        <div class="text-xs text-ink-600">{{ $angle['why_it_works'] ?? '' }}</div>
                                    </div>
                                    <form method="POST" action="{{ route('offers.ugc.content', $offer) }}" class="shrink-0">
                                        @csrf
                                        <input type="hidden" name="angles_generation_id" value="{{ $gen->id }}">
                                        <input type="hidden" name="angle_index" value="{{ $index }}">
                                        <button
                                            @if ($alreadyGenerated) disabled title="Already generated for this angle" @endif
                                            class="rounded-md border border-line text-ink-900 text-xs px-2.5 py-1.5 whitespace-nowrap hover:bg-surface-muted transition disabled:opacity-40 disabled:cursor-not-allowed"
                                        >{{ $alreadyGenerated ? 'Generated' : 'Generate this angle ('.config('credits.costs.ugc_content').')' }}</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @elseif ($gen->module === 'x_thread')
                        <details class="mb-3">
                            <summary class="cursor-pointer text-sm text-brand-600 hover:text-brand-700">View alternative hooks</summary>
                            <ul class="list-disc list-inside text-sm text-ink-900 mt-2 space-y-1">
                                @foreach ($gen->output_meta['hook_variants'] ?? [] as $hook)
                                    <li>{{ $hook }}</li>
                                @endforeach
                            </ul>
                        </details>
                        <div class="mb-2">@include('dashboard.offers._disclosure_badge', ['text' => $gen->output_meta['thread'][0]['text'] ?? ''])</div>
                        <div class="space-y-2 mb-3">
                            @foreach ($gen->output_meta['thread'] ?? [] as $tweet)
                                <div class="border-l-2 border-gold-500 pl-3">
                                    <div class="text-xs text-ink-400 font-mono">Tweet {{ $tweet['position'] ?? '' }}</div>
                                    {{-- Disclosure goes on the first tweet only — FTC-style guidance
                                         wants it "above the fold", not repeated (or missed) down a thread. --}}
                                    <div class="text-sm text-ink-900 whitespace-pre-line">{{ $offer->cloak($tweet['text'] ?? '', $gen->module, withDisclosure: $loop->first) }}</div>
                                </div>
                            @endforeach
                        </div>
                        <div class="text-xs text-ink-600"><span class="font-mono uppercase text-ink-400">Best time: </span>{{ $gen->output_meta['best_posting_time'] ?? '' }}</div>
                    @elseif ($gen->module === 'tiktok_video')
                        <details class="mb-3">
                            <summary class="cursor-pointer text-sm text-brand-600 hover:text-brand-700">View full script</summary>
                            <pre class="whitespace-pre-wrap text-sm text-ink-900 mt-3 font-sans">{{ $offer->cloak($gen->output_meta['script'] ?? '', $gen->module, withDisclosure: false) }}</pre>
                        </details>
                        @if (!empty($gen->output_meta['on_screen_text']))
                            <div class="text-xs uppercase text-ink-400 font-mono mb-1">On-screen text</div>
                            <ul class="list-disc list-inside text-sm text-ink-900 mb-3 space-y-0.5">
                                @foreach ($gen->output_meta['on_screen_text'] as $cue)
                                    <li><span class="font-mono text-xs text-ink-400">{{ $cue['timing'] ?? '' }}</span> {{ $cue['text'] ?? '' }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="mb-2">@include('dashboard.offers._disclosure_badge', ['text' => $gen->output_meta['caption'] ?? ''])</div>
                        <p class="text-sm text-ink-900 whitespace-pre-line mb-2">{{ $offer->cloak($gen->output_meta['caption'] ?? '', $gen->module) }}</p>
                        <div class="text-xs text-ink-600">{{ implode(', ', $gen->output_meta['hashtags'] ?? []) }}</div>
                    @elseif ($gen->module === 'ugc_content')
                        <details class="mb-3">
                            <summary class="cursor-pointer text-sm text-brand-600 hover:text-brand-700">View full script</summary>
                            <pre class="whitespace-pre-wrap text-sm text-ink-900 mt-3 font-sans">{{ $offer->cloak($gen->output_meta['script'] ?? '', $gen->module, withDisclosure: false) }}</pre>
                        </details>
                        @if (!empty($gen->output_meta['on_screen_text_ideas']))
                            <div class="text-xs uppercase text-ink-400 font-mono mb-1">On-screen text ideas</div>
                            <ul class="list-disc list-inside text-sm text-ink-900 mb-3 space-y-0.5">
                                @foreach ($gen->output_meta['on_screen_text_ideas'] as $idea)
                                    <li>{{ $idea }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="space-y-3">
                            @foreach ($gen->output_meta['platforms'] ?? [] as $platform)
                                <div class="border-l-2 border-gold-500 pl-3">
                                    <div class="text-sm font-medium text-ink-900">{{ $platform['platform'] ?? '' }}</div>
                                    <div class="text-sm text-ink-900">{{ $platform['title'] ?? '' }}</div>
                                    <div class="mb-1">@include('dashboard.offers._disclosure_badge', ['text' => $platform['caption'] ?? ''])</div>
                                    <div class="text-sm text-ink-900 whitespace-pre-line">{{ $offer->cloak($platform['caption'] ?? '', $gen->module) }}</div>
                                    <div class="text-xs text-ink-600">{{ implode(', ', $platform['tags'] ?? []) }}</div>
                                    <div class="text-xs text-ink-600"><span class="font-mono uppercase text-ink-400">Tip: </span>{{ $platform['posting_tip'] ?? '' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @elseif ($gen->module === 'email_nurture')
                        @php
                            $nurtureContact = \App\Models\CrmContact::find($gen->input['contact_id'] ?? null);
                        @endphp
                        <p class="text-xs text-ink-600 mb-3">
                            <span class="font-mono uppercase text-ink-400">To: </span>
                            {{ $nurtureContact ? $nurtureContact->name.($nurtureContact->company ? ' · '.$nurtureContact->company : '') : 'Contact no longer exists' }}
                        </p>
                        <div class="space-y-3">
                            @foreach ($gen->output_meta['emails'] ?? [] as $email)
                                <div class="border-l-2 border-gold-500 pl-3">
                                    <div class="text-xs text-ink-400 font-mono">Email {{ $email['step'] ?? '' }} &middot; {{ $email['send_timing'] ?? '' }}</div>
                                    <div class="text-sm font-medium text-ink-900">{{ $email['subject'] ?? '' }}</div>
                                    <div class="mb-1">@include('dashboard.offers._disclosure_badge', ['text' => $email['body'] ?? ''])</div>
                                    <div class="text-sm text-ink-900 whitespace-pre-line">{{ $offer->cloak($email['body'] ?? '', $gen->module) }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection
