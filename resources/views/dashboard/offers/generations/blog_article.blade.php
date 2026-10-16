@php
    $article = $offer->cloak($gen->output, $gen->module, generation: $gen);
    $wpService = app(\App\Services\WordPress\WordPressSiteService::class);
    $wpSites = $gen->status === 'completed' ? $wpService->owner(auth()->user())->wordpressSites()->connected()->orderBy('name')->get() : collect();
    $wpDeliveries = $gen->wordpressDeliveries()->with('site')->get()->keyBy('wordpress_site_id');
    $wpIncluded = $wpService->included(auth()->user());
@endphp
<h4 class="font-display text-lg font-semibold text-navy-900">{{ $gen->output_meta['title'] ?? 'Blog article' }}</h4>
@if (! empty($gen->output_meta['meta_description']))
    <p class="mt-1 text-sm text-ink-600"><span class="font-medium text-ink-900">Meta description:</span> {{ $gen->output_meta['meta_description'] }}</p>
@endif
<div class="mt-3 flex flex-wrap items-center gap-2">
    @include('dashboard.offers._disclosure_badge', ['text' => $gen->output])
    <x-ui.copy-button :text="$article" label="Copy article" />
    @if (! empty($gen->output_meta['title']))
        <x-ui.copy-button :text="$gen->output_meta['title']" label="Copy title" />
    @endif
</div>
<details class="group mt-3 rounded-2xl bg-surface-muted p-4">
    <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-medium text-brand-600">Read the full article <x-heroicon-m-chevron-down class="h-4 w-4 transition group-open:rotate-180" /></summary>
    <pre class="mt-3 max-h-[32rem] overflow-y-auto whitespace-pre-wrap font-sans text-sm leading-relaxed text-ink-900">{{ $article }}</pre>
</details>

@if ($gen->status === 'completed')
    <div class="mt-4 rounded-2xl border border-line p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="flex items-center gap-2 text-sm font-medium text-navy-900"><x-heroicon-o-globe-alt class="h-4 w-4 text-brand-600" /> WordPress</p>
            @if ($wpSites->isNotEmpty() && $wpIncluded)
                <form method="POST" action="{{ route('generations.wordpress', $gen) }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    @if ($wpSites->count() > 1)
                        <label for="wp-site-{{ $gen->id }}" class="sr-only">WordPress site</label>
                        <select id="wp-site-{{ $gen->id }}" name="site_id" class="rounded-full border border-line bg-white px-3 py-1.5 text-xs text-navy-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                            @foreach ($wpSites as $wpSite)
                                <option value="{{ $wpSite->id }}">{{ $wpSite->displayName() }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="site_id" value="{{ $wpSites->first()->id }}">
                    @endif
                    <x-ui.button size="sm" icon="paper-airplane">{{ $wpSites->count() > 1 ? 'Send to WordPress' : 'Send to '.$wpSites->first()->displayName() }}</x-ui.button>
                </form>
            @elseif ($wpIncluded)
                <a href="{{ route('wordpress.index') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Connect a WordPress site to send this as a draft →</a>
            @else
                <a href="{{ route('wordpress.index') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Send articles to WordPress as drafts →</a>
            @endif
        </div>
        @if ($wpDeliveries->isNotEmpty())
            <ul class="mt-3 space-y-1.5 text-xs">
                @foreach ($wpDeliveries as $delivery)
                    <li class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-navy-900">{{ $delivery->site?->displayName() ?? 'Removed site' }}</span>
                        @switch($delivery->status)
                            @case('sent')
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-800">{{ match ($delivery->wp_post_status) { 'publish' => 'Published', 'future' => 'Scheduled', 'private' => 'Published privately', 'trash' => 'In WordPress trash', default => 'Draft ready' } }}</span>
                                @if (in_array($delivery->wp_post_status, ['publish', 'future', 'private', 'trash'], true))
                                    <span class="text-ink-400">left unchanged</span>
                                @endif
                                @if ($delivery->edit_url)
                                    <a href="{{ $delivery->edit_url }}" target="_blank" rel="noopener" class="text-brand-600 hover:text-brand-700">Edit in WordPress</a>
                                @endif
                                @if ($delivery->sent_at)<span class="text-ink-400">{{ $delivery->sent_at->diffForHumans() }}</span>@endif
                                @break
                            @case('failed')
                                <span class="rounded-full bg-rose-100 px-2 py-0.5 text-rose-800">Not sent</span>
                                <span class="text-rose-700">{{ $delivery->error }}</span>
                                @break
                            @default
                                <span class="rounded-full bg-sky-100 px-2 py-0.5 text-sky-800">Sending…</span>
                        @endswitch
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
