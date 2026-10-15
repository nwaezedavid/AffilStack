@php
    $disclosures = [
        'Short (social posts)' => '#ad: I earn a commission if you sign up through my link.',
        'Standard (blogs, YouTube descriptions)' => 'Disclosure: some links on this page are affiliate links. If you sign up through them I may earn a commission, at no extra cost to you. I only recommend tools I use.',
        'Email P.S.' => 'P.S. That\'s my affiliate link: if you join I may earn a commission, and your price stays the same.',
        'Video (say it out loud too)' => 'Quick note: the link in the description is an affiliate link, so I may earn a commission if you sign up.',
    ];
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    <x-ui.card title="Do" icon="check-circle">
        <ul class="space-y-3 text-sm text-ink-900">
            @foreach ([
                'Disclose clearly, every time, next to the link. Put "#ad", "Affiliate link" or similar where people see it before they click.',
                'Talk about what AffilStack actually does: research, buyer-finding, AI content, tracking. Show your own results if you have them.',
                'Use the banners and copy in this kit, or write your own in your own words.',
                'Promote to people who opted in to hear from you: your list, followers, readers and communities where links are allowed.',
                'Send people to your own tracked link only. That\'s how you get credited.',
            ] as $item)
                <li class="flex gap-2.5"><x-heroicon-s-check-circle class="mt-0.5 h-5 w-5 shrink-0 text-emerald-500" /> <span>{{ $item }}</span></li>
            @endforeach
        </ul>
    </x-ui.card>

    <x-ui.card title="Don't" icon="no-symbol">
        <ul class="space-y-3 text-sm text-ink-900">
            @foreach ([
                'No brand bidding: don\'t bid on "AffilStack" or misspellings of it in Google, Bing, Meta or any other ads, and don\'t run ads that look like they come from us.',
                'No spam: no unsolicited email, DMs, comment spam, or bought or scraped lists.',
                'No income promises or invented results. Don\'t say people will earn a specific amount.',
                'No fake discounts, coupons or "limited time" claims we haven\'t announced.',
                'No cookie stuffing, pop-unders, hidden iframes, auto-redirects or incentivised clicks.',
                'No self-referrals: signing up yourself (or your own team) through your link doesn\'t earn commission.',
                'Don\'t register domains, social handles or app names containing "AffilStack".',
            ] as $item)
                <li class="flex gap-2.5"><x-heroicon-s-x-circle class="mt-0.5 h-5 w-5 shrink-0 text-rose-500" /> <span>{{ $item }}</span></li>
            @endforeach
        </ul>
    </x-ui.card>
</div>

<x-ui.card class="mt-6" title="Disclosure wording you can copy" description="The US FTC and the UK's ASA/CAP Code (and most other countries' advertising rules) require a clear, upfront disclosure whenever you can earn from a recommendation." icon="scale">
    <div class="grid gap-3 md:grid-cols-2">
        @foreach ($disclosures as $label => $text)
            <div class="rounded-xl bg-surface-muted p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-medium text-ink-900">{{ $label }}</p>
                    <x-ui.copy-button :text="$text" label="Copy" size="xs" />
                </div>
                <p class="mt-2 text-sm text-ink-900">{{ $text }}</p>
            </div>
        @endforeach
    </div>
    <p class="mt-4 text-xs text-ink-600">In the UK, ads on social media should be labelled "Ad" or "#ad" at the start. "#sp", "#spon" or a disclosure hidden after "see more" isn't enough. This is general guidance, not legal advice: follow the rules where you and your audience live.</p>
</x-ui.card>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <x-ui.card title="How attribution works" icon="finger-print" class="lg:col-span-2">
        <ol class="space-y-3 text-sm text-ink-900">
            <li class="flex gap-3"><span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[11px] font-semibold text-white">1</span> Someone clicks any of your links or banners. They go through <span class="font-mono text-xs bg-surface-muted rounded px-1.5 py-0.5">/r/{{ $user->referralCode() }}</span>.</li>
            <li class="flex gap-3"><span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[11px] font-semibold text-white">2</span> Their browser remembers you for {{ $cookieDays }} days. If several affiliates refer the same person, the most recent click wins.</li>
            <li class="flex gap-3"><span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[11px] font-semibold text-white">3</span> When they buy a plan, the sign-up is credited to you and you earn {{ $commissionPercent }}% of that payment and every renewal.</li>
            <li class="flex gap-3"><span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[11px] font-semibold text-white">4</span> Commissions are reviewed, then you can request a payout once you reach {{ \App\Support\ReferralProgram::money(\App\Support\ReferralProgram::minimumPayoutUsdCents()) }}. Refunded or charged-back payments are reversed.</li>
        </ol>
    </x-ui.card>
    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 sm:p-6">
        <p class="flex items-center gap-2 font-display text-sm font-semibold text-rose-900"><x-heroicon-o-exclamation-triangle class="h-5 w-5" /> Breaking the rules</p>
        <p class="mt-2 text-sm text-rose-900/80">We review traffic. Commissions earned against these guidelines are reversed, and repeated or serious breaches close the partner account.</p>
        <p class="mt-3 text-sm text-rose-900/80">Not sure if something's allowed? <a href="{{ route('contact.show') }}" class="font-medium underline">Ask us first</a>.</p>
    </div>
</div>
