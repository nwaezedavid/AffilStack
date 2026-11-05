@php
    $shareText = 'I use AffilStack to research affiliate offers, find buyers and write campaigns with AI. Worth a look:';
    $channelLabels = ['banner' => 'Banners', 'email' => 'Email', 'social' => 'Social', 'text_link' => 'Text links', 'link' => 'Direct link'];
    $channelMax = max(1, ...array_values($stats['by_source'] ?: [0]));
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <x-ui.card class="lg:col-span-2" title="Your referral link" description="Anyone who clicks it is tracked to you for {{ $cookieDays }} days." icon="link">
        <label for="kit-main-link" class="sr-only">Your referral link</label>
        <div class="flex flex-col gap-2 sm:flex-row">
            <input id="kit-main-link" type="text" readonly value="{{ $links['plain'] }}"
                   class="w-full min-w-0 rounded-xl border border-line bg-surface-muted px-3.5 py-2.5 font-mono text-sm text-navy-900 focus:outline-none focus:ring-2 focus:ring-brand-500"
                   onclick="this.select()">
            <x-ui.copy-button target="kit-main-link" label="Copy link" variant="primary" size="md" class="shrink-0" />
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-ink-600 mr-1">Share now:</span>
            <a href="https://twitter.com/intent/tweet?text={{ rawurlencode($shareText.' '.$links['plain']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-navy-900 ring-1 ring-line hover:bg-surface-muted"><x-ui.brand-icon name="x" class="h-3.5 w-3.5" /> X</a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ rawurlencode($links['plain']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-navy-900 ring-1 ring-line hover:bg-surface-muted"><x-ui.brand-icon name="linkedin" class="h-3.5 w-3.5" /> LinkedIn</a>
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($links['plain']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-navy-900 ring-1 ring-line hover:bg-surface-muted"><x-ui.brand-icon name="facebook" class="h-3.5 w-3.5" /> Facebook</a>
            <a href="https://wa.me/?text={{ rawurlencode($shareText.' '.$links['plain']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-navy-900 ring-1 ring-line hover:bg-surface-muted"><x-ui.brand-icon name="whatsapp" class="h-3.5 w-3.5" /> WhatsApp</a>
        </div>
    </x-ui.card>

    <div class="rounded-2xl bg-gradient-to-br {{ $user->isAffiliateOnly() ? 'from-emerald-950 via-emerald-900 to-teal-700' : 'from-navy-900 via-navy-800 to-brand-600' }} p-5 sm:p-6 text-white shadow-sm">
        <p class="text-[11px] font-mono uppercase tracking-[0.18em] text-gold-300">How you earn</p>
        <p class="mt-2 font-display text-4xl font-semibold">{{ $commissionPercent }}%</p>
        <p class="text-sm text-white/80">of every subscription payment, first and recurring, for as long as they stay subscribed. Credit top-ups don't count.</p>
        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-xl bg-white/10 p-3 ring-1 ring-white/15">
                <dt class="text-white/70 text-xs">Cookie window</dt>
                <dd class="font-semibold">{{ $cookieDays }} days</dd>
            </div>
            <div class="rounded-xl bg-white/10 p-3 ring-1 ring-white/15">
                <dt class="text-white/70 text-xs">Minimum payout</dt>
                <dd class="font-semibold">{{ \App\Support\ReferralProgram::money(\App\Support\ReferralProgram::minimumPayoutUsdCents()) }}</dd>
            </div>
        </dl>
    </div>
</div>

<div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-ui.stat label="Link clicks" :value="number_format($stats['clicks'])" icon="cursor-arrow-rays" :hint="number_format($stats['clicks_30d']).' in the last 30 days'" />
    <x-ui.stat label="Sign-ups" :value="number_format($stats['signups'])" icon="user-plus" tone="violet" hint="People who joined via you" />
    <x-ui.stat label="Paying customers" :value="number_format($stats['converted'])" icon="check-badge" tone="emerald" :hint="$stats['signups'] > 0 ? round($stats['converted'] / $stats['signups'] * 100).'% of sign-ups' : 'Earn on every subscription payment'" />
    <x-ui.stat label="Commission earned" :value="'$'.number_format($earnedCents / 100, 2)" icon="banknotes" tone="gold" :hint="'$'.number_format($totals['paid_cents'] / 100, 2).' paid out so far'" />
</div>

<x-ui.feature-intro class="mt-6" title="How the Marketing Kit works" :open="$stats['clicks'] === 0" :steps="[
    'Pick a banner, email, social post or text link. Your tracking code is already inside it.',
    'Paste it on your site, newsletter, YouTube description, socials or WhatsApp.',
    'Each visitor who clicks is remembered for '.$cookieDays.' days, even if they buy later.',
    'You earn '.$commissionPercent.'% of their first payment and every renewal after it.',
]" />

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <x-ui.card class="lg:col-span-2" title="Quick-start ideas" description="Where AffilStack partners get their first sign-ups." icon="rocket-launch">
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ([
                ['icon' => 'photo', 'title' => 'Add a banner to your site', 'body' => 'A 300×250 in your sidebar or a 728×90 above your posts. The JS snippet rotates designs for you.', 'tab' => 'banners'],
                ['icon' => 'envelope', 'title' => 'Email your list', 'body' => 'Six ready-made emails for different angles. Your link and sign-off are already filled in.', 'tab' => 'emails'],
                ['icon' => 'megaphone', 'title' => 'Post on social', 'body' => 'Posts for LinkedIn, X, Facebook, Instagram, WhatsApp and TikTok with one-click share.', 'tab' => 'social'],
                ['icon' => 'document-text', 'title' => 'Write a review or tutorial', 'body' => 'Use the product descriptions and text links in a "tools I use" or how-to post.', 'tab' => 'links'],
            ] as $idea)
                <a href="{{ $kitTabUrl($idea['tab']) }}" data-tab-link="{{ $idea['tab'] }}"
                   class="group flex gap-3 rounded-xl border border-line p-4 transition hover:border-brand-100 hover:bg-brand-50/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 group-hover:bg-white">
                        <x-dynamic-component :component="'heroicon-o-'.$idea['icon']" class="h-5 w-5" />
                    </span>
                    <span>
                        <span class="block font-display text-sm font-semibold text-navy-900">{{ $idea['title'] }}</span>
                        <span class="mt-0.5 block text-sm text-ink-600">{{ $idea['body'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card title="Clicks by channel" description="Where your clicks come from." icon="chart-bar">
        @if ($stats['clicks'] === 0)
            <p class="text-sm text-ink-600">No clicks yet. Once people start clicking your links, you'll see which channel works best here.</p>
        @else
            <ul class="space-y-3">
                @foreach ($channelLabels as $source => $label)
                    @php($count = $stats['by_source'][$source] ?? 0)
                    <li>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-ink-900">{{ $label }}</span>
                            <span class="font-mono text-ink-600 tabular-nums">{{ number_format($count) }}</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-surface-muted overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-gold-400" style="width: {{ $count > 0 ? max(4, round($count / $channelMax * 100)) : 0 }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>

<x-ui.next-step class="mt-6" :href="$kitTabUrl('banners')" action="Browse banners" data-tab-link="banners">
    @if ($stats['clicks'] === 0)
        Start with one banner on a page that already gets traffic. It takes two minutes and keeps working while you sleep.
    @else
        Check which banners get clicked most, then put your best one in more places.
    @endif
</x-ui.next-step>
