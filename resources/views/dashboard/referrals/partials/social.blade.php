@php
    $posts = $swipes->get('social', collect());
    $platforms = config('affiliate_kit.social_platforms');
    $icons = ['linkedin' => 'linkedin', 'x' => 'x', 'facebook' => 'facebook', 'instagram' => 'instagram', 'whatsapp' => 'whatsapp', 'tiktok' => 'tiktok'];
    $limits = ['x' => 280, 'linkedin' => 3000, 'instagram' => 2200, 'tiktok' => 2200, 'whatsapp' => 700, 'facebook' => 5000];
    $shareUrl = function (array $post): ?string {
        return match ($post['platform']) {
            'x' => 'https://twitter.com/intent/tweet?text='.rawurlencode($post['body']),
            'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url='.rawurlencode($post['link']),
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($post['link']),
            'whatsapp' => 'https://wa.me/?text='.rawurlencode($post['body']),
            default => null,
        };
    };
@endphp

<div class="mb-6 grid gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-2xl border border-line bg-surface p-5 sm:p-6">
        <h3 class="font-display font-semibold text-base text-navy-900">Posts for every platform</h3>
        <p class="mt-1 text-sm text-ink-600">Copy a post, or use the share button to open it ready to publish. LinkedIn and Facebook only let us pre-fill the link, so copy the text first and paste it into the post.</p>
    </div>
    <div class="rounded-2xl border border-line bg-surface p-5 sm:p-6">
        <label for="kit-bio-link" class="text-xs font-medium text-ink-900">Link for your bio (Instagram, TikTok)</label>
        <div class="mt-1.5 flex gap-2">
            <input id="kit-bio-link" type="text" readonly value="{{ $links['bio'] }}" class="w-full min-w-0 rounded-xl border border-line bg-surface-muted px-3 py-2 font-mono text-xs text-navy-900" onclick="this.select()">
            <x-ui.copy-button target="kit-bio-link" label="Copy" class="shrink-0" />
        </div>
        <a href="{{ route('referrals.index', ['tab' => 'banners']) }}" data-tab-link="banners" data-size-filter="social" class="mt-3 inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700">Get square, link-post and story images <x-heroicon-m-arrow-right class="h-3.5 w-3.5" /></a>
    </div>
</div>

@if ($posts->isEmpty())
    <x-ui.empty-state icon="megaphone" title="No social posts yet">The AffilStack team hasn't published any social posts yet. Check back soon.</x-ui.empty-state>
@else
    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($posts as $post)
            @php($url = $shareUrl($post))
            @php($limit = $limits[$post['platform']] ?? null)
            <article class="flex flex-col rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
                <header class="flex items-center gap-3 border-b border-line px-5 py-3.5">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-surface-muted">
                        <x-ui.brand-icon :name="$icons[$post['platform']] ?? 'mail'" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-400">{{ $platforms[$post['platform']] ?? ucfirst((string) $post['platform']) }}</p>
                        <h4 class="font-display text-sm font-semibold text-navy-900 truncate">{{ $post['title'] }}</h4>
                    </div>
                </header>
                <pre id="social-post-{{ $post['id'] }}" class="flex-1 whitespace-pre-wrap break-words px-5 py-4 font-sans text-sm leading-relaxed text-ink-900">{{ $post['body'] }}</pre>
                <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-5 py-3">
                    @if ($limit)
                        @php($length = mb_strlen($post['platform'] === 'x' ? preg_replace('~https?://\S+~', str_repeat('x', 23), $post['body']) : $post['body']))
                        <span class="text-[11px] tabular-nums {{ $length > $limit ? 'text-rose-600 font-semibold' : 'text-ink-400' }}">{{ number_format($length) }} / {{ number_format($limit) }} characters</span>
                    @endif
                    <div class="flex gap-2 ml-auto">
                        <x-ui.copy-button :target="'social-post-'.$post['id']" label="Copy" size="xs" />
                        @if ($url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-navy-900 px-2.5 py-1 text-[11px] font-medium text-white hover:bg-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                                <x-heroicon-m-share class="h-3.5 w-3.5" /> Share
                            </a>
                        @endif
                    </div>
                </footer>
            </article>
        @endforeach
    </div>
@endif

<x-ui.next-step class="mt-6" :href="route('referrals.index', ['tab' => 'guidelines'])" action="Read the guidelines" data-tab-link="guidelines">
    Before you post, make sure you've included a clear disclosure like "#ad" or "affiliate link". It's the law in most countries and keeps your commissions safe.
</x-ui.next-step>
