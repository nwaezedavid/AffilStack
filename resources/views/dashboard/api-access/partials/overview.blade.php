@php
    $groupInfo = [
        'Account' => ['icon' => 'user-circle', 'tone' => 'bg-brand-50 text-brand-600', 'text' => 'Check your token works, see your plan, credit balance and API wallet.'],
        'Offers' => ['icon' => 'magnifying-glass-circle', 'tone' => 'bg-violet-50 text-violet-600', 'text' => 'List your products and start AI product research from any tool.'],
        'Generations' => ['icon' => 'document-text', 'tone' => 'bg-gold-300/25 text-gold-600', 'text' => 'Pull finished blog articles, posts, scripts and videos into WordPress, Notion or your CMS.'],
        'CRM Contacts' => ['icon' => 'users', 'tone' => 'bg-emerald-50 text-emerald-600', 'text' => 'Add, update and export leads — one at a time or 100 per call.'],
        'Referrals' => ['icon' => 'gift', 'tone' => 'bg-rose-50 text-rose-600', 'text' => 'Your referral link, conversions and unpaid commission totals.'],
        'Zapier' => ['icon' => 'bolt', 'tone' => 'bg-orange-50 text-orange-600', 'text' => 'Subscribe any URL to live events (REST Hooks) — what our Zapier app uses.'],
    ];
    $useCases = [
        ['icons' => ['zapier', 'mailchimp'], 'title' => 'Send new CRM contacts to Google Sheets or Mailchimp', 'text' => 'Every lead you add — from Local Leads, the Chrome extension or the dashboard — lands in your sheet or email list automatically.', 'how' => 'Webhook event crm_contact.created + Zapier or Make', 'tab' => 'no-code'],
        ['icons' => ['make'], 'title' => 'Start product research from n8n or Make', 'text' => 'A new row in your product tracker, a form submission or a Slack command kicks off AffilStack research — no logging in.', 'how' => 'POST /offers from an HTTP step', 'tab' => 'no-code'],
        ['icons' => ['wordpress'], 'title' => 'Publish generated articles to WordPress', 'text' => 'When a blog article finishes generating, send it to WordPress as a draft, ready for your final edit.', 'how' => 'generation.completed webhook + GET /generations/{id}', 'tab' => 'webhooks'],
        ['icons' => ['javascript', 'python'], 'title' => 'Build your own dashboard or app', 'text' => 'Show your products, content, leads and referral earnings inside your own site, Notion or Looker Studio.', 'how' => 'GET /offers, /generations, /referrals/summary', 'tab' => 'code'],
        ['icons' => ['hubspot'], 'title' => 'Import leads from any form or CRM', 'text' => 'Move contacts in from HubSpot, Typeform, Facebook Lead Ads or a CSV — up to 100 in one call, safely retried.', 'how' => 'POST /crm-contacts/bulk', 'tab' => 'reference'],
        ['icons' => ['whatsapp', 'telegram'], 'title' => 'Get pinged when money comes in', 'text' => 'A WhatsApp, Telegram or Slack message the moment an earning is recorded or a referral converts.', 'how' => 'earning.recorded / referral.converted webhooks', 'tab' => 'webhooks'],
    ];
@endphp

<x-ui.card padding="p-5 sm:p-6">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)] lg:items-center">
        <div>
            <p class="text-[11px] font-mono uppercase tracking-[0.16em] text-brand-600">What is the AffilStack API?</p>
            <h3 class="mt-1 font-display text-xl font-semibold text-navy-900 text-balance">Let your other tools talk to AffilStack — no copy-and-paste.</h3>
            <p class="mt-2 text-sm text-ink-600">
                An API is a secure doorway other apps use to read and add data in your AffilStack account. With one
                token you can push leads into your CRM, pull generated content out, start research, and get notified
                the moment something happens — from no-code tools like Zapier, Make and n8n, or from your own code.
                Every call only ever sees what you can already see in your dashboard.
            </p>
        </div>
        <div class="grid gap-2.5">
            @foreach ([
                ['no-code', 'puzzle-piece', 'No-code tools', 'Zapier, Make, n8n, Google Sheets, Pabbly — click-by-click recipes.'],
                ['webhooks', 'bolt', 'Instant events', 'AffilStack calls your URL the moment a lead, article or sale happens.'],
                ['code', 'code-bracket', 'Your own code', 'Copy-paste samples in cURL, JavaScript, Node, Python and PHP.'],
            ] as [$tab, $icon, $title, $text])
                <a href="{{ route('api-access.index', ['tab' => $tab]) }}" data-tab-link="{{ $tab }}" class="group flex items-start gap-3 rounded-xl border border-line p-3 transition hover:border-brand-500/40 hover:bg-brand-50/40">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-navy-900 text-gold-300"><x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" /></span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-navy-900">{{ $title }}</span>
                        <span class="block text-xs text-ink-600">{{ $text }}</span>
                    </span>
                    <x-heroicon-m-arrow-right class="ml-auto mt-1 h-4 w-4 shrink-0 text-ink-400 transition group-hover:translate-x-0.5 group-hover:text-brand-600" />
                </a>
            @endforeach
        </div>
    </div>
</x-ui.card>

<div>
    <h3 class="font-display text-lg font-semibold text-navy-900">What people build with it</h3>
    <p class="text-sm text-ink-600">Real automations AffilStack users set up in an afternoon.</p>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($useCases as $case)
            <div class="flex flex-col rounded-2xl border border-line bg-surface p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
                <div class="flex items-center gap-1.5">
                    @foreach ($case['icons'] as $icon)
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-surface-muted ring-1 ring-line"><x-ui.brand-icon :name="$icon" class="h-5 w-5" /></span>
                    @endforeach
                </div>
                <h4 class="mt-3 font-display text-[15px] font-semibold text-navy-900">{{ $case['title'] }}</h4>
                <p class="mt-1 text-sm text-ink-600">{{ $case['text'] }}</p>
                <p class="mt-3 rounded-lg bg-surface-muted px-2.5 py-1.5 font-mono text-[11px] text-ink-600">{{ $case['how'] }}</p>
                <a href="{{ route('api-access.index', ['tab' => $case['tab']]) }}" data-tab-link="{{ $case['tab'] }}" class="mt-auto pt-3 inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700">
                    Show me how <x-heroicon-m-arrow-right class="h-3.5 w-3.5" />
                </a>
            </div>
        @endforeach
    </div>
</div>

<x-ui.card title="Everything the API can do" description="Every endpoint group, generated from the live API specification." icon="squares-2x2">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @foreach ($endpointGroups as $tag => $groupEndpoints)
            @php($info = $groupInfo[$tag] ?? ['icon' => 'cube', 'tone' => 'bg-surface-muted text-ink-600', 'text' => ''])
            <div class="rounded-xl border border-line p-4">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $info['tone'] }}"><x-dynamic-component :component="'heroicon-o-'.$info['icon']" class="h-5 w-5" /></span>
                    <div class="min-w-0">
                        <p class="font-display font-semibold text-navy-900">{{ $tag }} <span class="ml-1 text-xs font-normal text-ink-400">{{ count($groupEndpoints) }} endpoint{{ count($groupEndpoints) === 1 ? '' : 's' }}</span></p>
                        <p class="text-xs text-ink-600">{{ $info['text'] }}</p>
                    </div>
                </div>
                <ul class="mt-3 space-y-1.5">
                    @foreach ($groupEndpoints as $endpoint)
                        <li>
                            <a href="{{ route('api-access.index', ['tab' => 'reference']) }}#{{ $endpoint['anchor'] }}" data-tab-link="reference" data-target="{{ $endpoint['anchor'] }}" class="flex items-center gap-2 rounded-md px-1 py-0.5 text-xs hover:bg-surface-muted">
                                <x-api.method-badge :method="$endpoint['method']" />
                                <span class="truncate font-mono text-navy-900">{{ $endpoint['path'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</x-ui.card>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <x-ui.stat label="Your rate limit" :value="$currentRateLimit.' / min'" icon="bolt" tone="brand" :hint="($planNames[$currentPlanSlug] ?? 'Your').' plan, per token'" />
    <x-ui.stat label="Reading data" value="Free" icon="arrow-down-tray" tone="emerald" hint="every GET endpoint" />
    <x-ui.stat label="Paid calls" :value="'$'.number_format(collect(config('api_billing.costs'))->max() / 100, 2)" icon="banknotes" tone="gold" hint="per product research run, from your API wallet" />
</div>

<x-ui.next-step :href="route('api-access.index', ['tab' => 'quick-start'])" action="Create my first token" data-tab-link="quick-start">
    Create a token, then make your first request in under two minutes — the Quick start walks you through it.
</x-ui.next-step>
