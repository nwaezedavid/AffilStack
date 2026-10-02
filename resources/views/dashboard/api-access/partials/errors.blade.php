@php
    $statusCodes = [
        ['200 / 201', 'Success', 'The request worked. 201 means something was created.', 'emerald'],
        ['401', 'Unauthorized', 'The token is missing, mistyped or revoked. Check the Authorization header is exactly "Bearer aff_…".', 'rose'],
        ['402', 'Payment required', 'Not enough plan credits, API wallet balance or CRM contact allowance. Top up or upgrade, then retry.', 'rose'],
        ['403', 'Forbidden', 'A read-only token tried to write, or a team seat tried an owner-only action. Use a read & write token.', 'rose'],
        ['404', 'Not found', 'The record doesn\'t exist, isn\'t yours, or belongs to the other mode (sandbox vs live).', 'amber'],
        ['422', 'Validation failed', 'Something in your data is wrong. The "errors" object says which field and why.', 'amber'],
        ['429', 'Too many requests', 'You went over your plan\'s per-minute limit. Wait the number of seconds in the Retry-After header.', 'amber'],
        ['5xx', 'Server error', 'Something broke on our side. Retry after a short pause; contact support if it continues.', 'rose'],
    ];
    $errorExample = json_encode(['message' => 'The email field must be a valid email address.', 'errors' => ['email' => ['The email field must be a valid email address.']]], JSON_PRETTY_PRINT);
    $pageExample = json_encode(['data' => ['…25 items…'], 'current_page' => 1, 'last_page' => 4, 'per_page' => 25, 'total' => 93, 'next_page_url' => $baseUrl.'/crm-contacts?page=2'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
@endphp

<div>
    <h3 class="font-display text-lg font-semibold text-navy-900">Errors & best practices</h3>
    <p class="text-sm text-ink-600">What to do when something goes wrong, and the habits that keep integrations reliable and secure.</p>
</div>

<x-ui.card title="Error format" description="Every error is JSON with a human-readable message. Validation errors add an errors object, keyed by field." icon="exclamation-circle">
    <x-ui.code-block :code="$errorExample" label="422 Unprocessable Content" />
</x-ui.card>

<x-ui.card title="Status codes" icon="signal" padding="p-0">
    <div class="divide-y divide-line">
        @foreach ($statusCodes as [$code, $name, $meaning, $tone])
            <div class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-start sm:gap-4">
                <span @class([
                    'inline-flex w-20 shrink-0 justify-center rounded-md px-2 py-0.5 font-mono text-xs font-semibold ring-1 ring-inset',
                    'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => $tone === 'emerald',
                    'bg-amber-50 text-amber-700 ring-amber-600/20' => $tone === 'amber',
                    'bg-rose-50 text-rose-700 ring-rose-600/20' => $tone === 'rose',
                ])>{{ $code }}</span>
                <p class="text-sm text-ink-600"><span class="font-semibold text-navy-900">{{ $name }}.</span> {{ $meaning }}</p>
            </div>
        @endforeach
    </div>
</x-ui.card>

<div class="grid gap-6 lg:grid-cols-2">
    <x-ui.card title="Pagination" icon="queue-list">
        <p class="text-sm text-ink-600">List endpoints return 25 items by default. Add <code class="rounded bg-surface-muted px-1 font-mono text-xs">?per_page=100</code> (the maximum) and walk the pages with <code class="rounded bg-surface-muted px-1 font-mono text-xs">?page=2</code>, <code class="rounded bg-surface-muted px-1 font-mono text-xs">3</code>… until <code class="rounded bg-surface-muted px-1 font-mono text-xs">current_page</code> equals <code class="rounded bg-surface-muted px-1 font-mono text-xs">last_page</code>.</p>
        <x-ui.code-block class="mt-3" :code="$pageExample" label="Paginated response" />
    </x-ui.card>

    <x-ui.card title="Idempotency keys" icon="arrow-path">
        <p class="text-sm text-ink-600">Networks fail. If a create times out you can't tell whether it worked — so send an <code class="rounded bg-surface-muted px-1 font-mono text-xs">Idempotency-Key</code> header (any unique string up to 255 characters, like your own order or row ID) on <code class="rounded bg-surface-muted px-1 font-mono text-xs">POST /offers</code> and <code class="rounded bg-surface-muted px-1 font-mono text-xs">POST /crm-contacts</code>.</p>
        <ul class="mt-3 space-y-1.5 text-sm text-ink-600">
            <li class="flex gap-2"><x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" /> Same key + same body → you get the original response back; nothing is created or charged twice.</li>
            <li class="flex gap-2"><x-heroicon-m-x-mark class="mt-0.5 h-4 w-4 shrink-0 text-rose-600" /> Same key + different body → 422, so a bug can't silently reuse a key.</li>
            <li class="flex gap-2"><x-heroicon-m-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-ink-400" /> Bulk contacts aren't keyed: retry only the items listed in <code class="font-mono text-xs">failed</code>.</li>
        </ul>
    </x-ui.card>

    <x-ui.card title="Sandbox tokens" icon="beaker">
        <p class="text-sm text-ink-600">Tick "Sandbox token" when creating a token to get a safe playground, just like Stripe's test mode:</p>
        <ul class="mt-3 space-y-1.5 text-sm text-ink-600">
            <li class="flex gap-2"><x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" /> Never spends wallet balance or plan credits.</li>
            <li class="flex gap-2"><x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" /> <code class="font-mono text-xs">POST /offers</code> returns sample research instantly.</li>
            <li class="flex gap-2"><x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" /> Sandbox and live data never mix — each only sees its own records, and sandbox data never fires real webhooks.</li>
        </ul>
    </x-ui.card>

    <x-ui.card title="Rate limits" icon="clock">
        <p class="text-sm text-ink-600">Your plan allows <span class="font-semibold text-navy-900">{{ $currentRateLimit }} requests a minute per token</span>. Going over returns 429 with a <code class="rounded bg-surface-muted px-1 font-mono text-xs">Retry-After</code> header in seconds — wait that long, then continue. For big jobs, use bulk endpoints (100 contacts per call) and <code class="rounded bg-surface-muted px-1 font-mono text-xs">per_page=100</code> instead of many small calls, and prefer webhooks over polling.</p>
    </x-ui.card>
</div>

<x-ui.card title="Keep your account safe" icon="shield-check">
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ([
            ['eye-slash', 'Never expose a token in front-end code', 'Anything in website JavaScript, a mobile app or a browser extension can be read by anyone. Call the API from a server or an automation tool.'],
            ['key', 'One token per integration', 'Name each token after where it\'s used. If one leaks, revoke just that one — everything else keeps working.'],
            ['lock-closed', 'Use the least access needed', 'Reports, dashboards and Google Sheets only need a read-only token, which can never write data or spend money.'],
            ['archive-box', 'Store tokens as secrets', 'Use your tool\'s credential store, environment variables or Apps Script properties — never commit them to Git or paste them in shared docs.'],
            ['finger-print', 'Verify webhook signatures', 'On your own server, check X-AffilStack-Signature so nobody can fake an AffilStack event.'],
            ['arrow-path-rounded-square', 'Rotate and tidy up', 'Revoke tokens you no longer use. The Quick start tab shows when each was last used.'],
        ] as [$icon, $title, $text])
            <div class="flex gap-3">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-navy-900 text-gold-300"><x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" /></span>
                <div>
                    <p class="text-sm font-semibold text-navy-900">{{ $title }}</p>
                    <p class="text-sm text-ink-600">{{ $text }}</p>
                </div>
            </div>
        @endforeach
    </div>
</x-ui.card>

<x-ui.next-step :href="route('support.index')" action="Contact support">
    Stuck on an error? Send support the request you made and the response you got (never your token) and we'll help.
</x-ui.next-step>
