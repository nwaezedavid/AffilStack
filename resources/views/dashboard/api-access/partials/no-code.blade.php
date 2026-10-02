@php
    $tokenHint = 'Bearer YOUR_API_TOKEN';
    $offerJson = json_encode(['product_name' => '{{product name}}', 'product_url' => '{{product url}}', 'affiliate_network' => 'ShareASale'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $webhooksUrl = route('api-access.index', ['tab' => 'webhooks']);
    $appsScript = <<<JS
// AffilStack → Google Sheets: copies your CRM contacts into a "Contacts" tab.
const BASE_URL = '{$baseUrl}';

function importAffilStackContacts() {
  const token = PropertiesService.getScriptProperties().getProperty('AFFILSTACK_TOKEN');
  const book = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = book.getSheetByName('Contacts') || book.insertSheet('Contacts');

  const response = UrlFetchApp.fetch(BASE_URL + '/crm-contacts?per_page=100', {
    headers: { Authorization: 'Bearer ' + token, Accept: 'application/json' },
    muteHttpExceptions: true,
  });
  if (response.getResponseCode() !== 200) {
    throw new Error('AffilStack API error ' + response.getResponseCode() + ': ' + response.getContentText());
  }

  const contacts = JSON.parse(response.getContentText()).data;
  const rows = contacts.map(function (c) {
    return [c.id, c.name, c.email, c.company, c.phone, c.status, c.created_at];
  });

  sheet.clearContents();
  sheet.appendRow(['ID', 'Name', 'Email', 'Company', 'Phone', 'Status', 'Added']);
  if (rows.length) {
    sheet.getRange(2, 1, rows.length, rows[0].length).setValues(rows);
  }
}
JS;
@endphp

<div>
    <h3 class="font-display text-lg font-semibold text-navy-900">No-code tools</h3>
    <p class="text-sm text-ink-600">Click-by-click recipes with the exact values to paste. Every recipe uses <span class="font-medium text-navy-900">your</span> base URL — just swap in your token.</p>
</div>

<x-ui.feature-intro title="Two directions, one token" :collapsible="false" :steps="[
    'Out of AffilStack (instant): add the tool\'s webhook URL on the Webhooks tab — AffilStack sends every new lead, article or sale to it.',
    'Into AffilStack: an HTTP step in your tool calls our API with your token to add contacts or start research.',
    'Test safely with a sandbox token and the “Send test event” button before going live.',
]" />

{{-- Zapier --}}
<details id="recipe-zapier" class="group rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)]" open>
    <summary class="flex cursor-pointer list-none items-center gap-3 px-5 py-4">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-surface-muted ring-1 ring-line"><x-ui.brand-icon name="zapier" class="h-5 w-5" /></span>
        <span class="min-w-0 flex-1">
            <span class="block font-display font-semibold text-navy-900">Zapier</span>
            <span class="block text-xs text-ink-600">Connect AffilStack to 7,000+ apps — Google Sheets, Mailchimp, Slack, HubSpot…</span>
        </span>
        <x-heroicon-m-chevron-down class="h-5 w-5 text-ink-400 transition group-open:rotate-180" />
    </summary>
    <div class="space-y-5 border-t border-line px-5 py-5">
        <div class="rounded-xl bg-orange-50/60 p-4 ring-1 ring-orange-200">
            <p class="text-sm font-semibold text-navy-900">Option A — the AffilStack Zapier app</p>
            <p class="mt-1 text-sm text-ink-600">
                Our own Zapier app gives you two <span class="font-medium">instant triggers</span> — <em>New Offer Research Completed</em> and
                <em>New CRM Contact</em> — and two <span class="font-medium">actions</span> — <em>Create Offer</em> (product research) and
                <em>Create CRM Contact</em>. When Zapier asks you to connect AffilStack, enter:
            </p>
            <dl class="mt-3 rounded-lg bg-white px-3 ring-1 ring-orange-100">
                <x-api.field-row label="AffilStack API URL" :value="$baseUrl" />
                <x-api.field-row label="API Token" value="Your token from the Quick start tab" :mono="false" :copy="false" />
            </dl>
            @if ($zapierInviteUrl)
                <x-ui.button class="mt-3" variant="primary" :href="$zapierInviteUrl" icon="arrow-top-right-on-square" target="_blank" rel="noopener">Open AffilStack on Zapier</x-ui.button>
            @else
                <p class="mt-3 text-xs text-ink-600"><span class="font-semibold text-orange-700">Private beta:</span> the app isn't in Zapier's public directory yet. Ask support for an invite link — or use Option B below, which works today with no app at all.</p>
            @endif
            <p class="mt-2 text-xs text-ink-600">Each trigger you turn on appears on the Webhooks tab, where you can see its deliveries or switch it off.</p>
        </div>

        <div>
            <p class="text-sm font-semibold text-navy-900">Option B — send new AffilStack contacts to Google Sheets (Webhooks by Zapier)</p>
            <ol class="mt-2 space-y-2 text-sm text-ink-900">
                <li class="flex gap-2"><span class="font-mono text-xs text-ink-400">1.</span> In Zapier, create a Zap. Trigger app: <strong>Webhooks by Zapier</strong>, event: <strong>Catch Hook</strong>. Copy the <em>Custom Webhook URL</em> it gives you.</li>
                <li class="flex gap-2"><span class="font-mono text-xs text-ink-400">2.</span> Open the <a href="{{ $webhooksUrl }}" data-tab-link="webhooks" class="font-medium text-brand-600 underline">Webhooks tab</a>, paste the URL, tick <strong>New CRM contact created</strong>, and click <strong>Add endpoint</strong>.</li>
                <li class="flex gap-2"><span class="font-mono text-xs text-ink-400">3.</span> Click <strong>Send test event</strong> on the new endpoint, then <strong>Test trigger</strong> in Zapier — you'll see fields like <code class="rounded bg-surface-muted px-1 font-mono text-xs">Data Name</code>, <code class="rounded bg-surface-muted px-1 font-mono text-xs">Data Email</code>, <code class="rounded bg-surface-muted px-1 font-mono text-xs">Data Company</code>.</li>
                <li class="flex gap-2"><span class="font-mono text-xs text-ink-400">4.</span> Add an action: <strong>Google Sheets → Create Spreadsheet Row</strong> (or <strong>Mailchimp → Add/Update Subscriber</strong>) and map those fields. Publish.</li>
            </ol>
        </div>

        <div>
            <p class="text-sm font-semibold text-navy-900">Option C — add contacts to AffilStack from any Zapier trigger</p>
            <p class="mt-1 text-sm text-ink-600">Action app <strong>Webhooks by Zapier</strong>, event <strong>POST</strong>, then fill in:</p>
            <dl class="mt-2 rounded-xl border border-line px-3">
                <x-api.field-row label="URL" :value="$baseUrl.'/crm-contacts'" />
                <x-api.field-row label="Payload Type" value="json" />
                <x-api.field-row label="Data" value="name, email, company, phone, website, notes → map from your trigger" :mono="false" :copy="false" />
                <x-api.field-row label="Wrap Request In Array" value="No" :copy="false" />
                <x-api.field-row label="Unflatten" value="Yes" :copy="false" />
                <x-api.field-row label="Headers: Authorization" :value="$tokenHint" />
                <x-api.field-row label="Headers: Accept" value="application/json" />
            </dl>
            <p class="mt-2 text-xs text-ink-600">Webhooks by Zapier needs a paid Zapier plan.</p>
        </div>
    </div>
</details>

{{-- Make --}}
<details id="recipe-make" class="group rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
    <summary class="flex cursor-pointer list-none items-center gap-3 px-5 py-4">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-surface-muted ring-1 ring-line"><x-ui.brand-icon name="make" class="h-5 w-5" /></span>
        <span class="min-w-0 flex-1">
            <span class="block font-display font-semibold text-navy-900">Make (formerly Integromat)</span>
            <span class="block text-xs text-ink-600">Visual scenarios with the HTTP and Webhooks modules — works on Make's free plan.</span>
        </span>
        <x-heroicon-m-chevron-down class="h-5 w-5 text-ink-400 transition group-open:rotate-180" />
    </summary>
    <div class="space-y-5 border-t border-line px-5 py-5">
        <div>
            <p class="text-sm font-semibold text-navy-900">Start product research from a Make scenario</p>
            <p class="mt-1 text-sm text-ink-600">Add the <strong>HTTP → Make a request</strong> module after your trigger (e.g. Google Sheets → Watch New Rows) and set:</p>
            <dl class="mt-2 rounded-xl border border-line px-3">
                <x-api.field-row label="URL" :value="$baseUrl.'/offers'" />
                <x-api.field-row label="Method" value="POST" :copy="false" />
                <x-api.field-row label="Headers → Authorization" :value="$tokenHint" />
                <x-api.field-row label="Headers → Accept" value="application/json" />
                <x-api.field-row label="Body type" value="Raw" :copy="false" />
                <x-api.field-row label="Content type" value="JSON (application/json)" :copy="false" />
                <x-api.field-row label="Parse response" value="Yes" :copy="false" />
            </dl>
            <p class="mt-3 text-sm text-ink-600">Request content (map the two placeholders from your trigger):</p>
            <x-ui.code-block class="mt-2" :code="$offerJson" label="Request content" />
            <p class="mt-2 text-xs text-ink-600">Each live research run costs ${{ number_format((config('api_billing.costs')['api.v1.offers.store'] ?? 0) / 100, 2) }} from your API wallet plus the usual research credits. Use a sandbox token while you test.</p>
        </div>
        <div>
            <p class="text-sm font-semibold text-navy-900">React to AffilStack events in Make</p>
            <ol class="mt-2 space-y-2 text-sm text-ink-900">
                <li class="flex gap-2"><span class="font-mono text-xs text-ink-400">1.</span> Add <strong>Webhooks → Custom webhook</strong> as the first module, click <strong>Add</strong>, name it "AffilStack" and copy the address.</li>
                <li class="flex gap-2"><span class="font-mono text-xs text-ink-400">2.</span> Add that address on the <a href="{{ $webhooksUrl }}" data-tab-link="webhooks" class="font-medium text-brand-600 underline">Webhooks tab</a> with the events you want.</li>
                <li class="flex gap-2"><span class="font-mono text-xs text-ink-400">3.</span> In Make click <strong>Run once</strong>, then <strong>Send test event</strong> in AffilStack — Make learns the fields (<code class="rounded bg-surface-muted px-1 font-mono text-xs">event</code>, <code class="rounded bg-surface-muted px-1 font-mono text-xs">data.email</code>…).</li>
            </ol>
        </div>
    </div>
</details>

{{-- n8n --}}
<details id="recipe-n8n" class="group rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
    <summary class="flex cursor-pointer list-none items-center gap-3 px-5 py-4">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-rose-100"><x-heroicon-o-share class="h-5 w-5" /></span>
        <span class="min-w-0 flex-1">
            <span class="block font-display font-semibold text-navy-900">n8n</span>
            <span class="block text-xs text-ink-600">Self-hosted or cloud workflows with the HTTP Request and Webhook nodes.</span>
        </span>
        <x-heroicon-m-chevron-down class="h-5 w-5 text-ink-400 transition group-open:rotate-180" />
    </summary>
    <div class="space-y-5 border-t border-line px-5 py-5">
        <div>
            <p class="text-sm font-semibold text-navy-900">Trigger AffilStack research with the HTTP Request node</p>
            <dl class="mt-2 rounded-xl border border-line px-3">
                <x-api.field-row label="Method" value="POST" :copy="false" />
                <x-api.field-row label="URL" :value="$baseUrl.'/offers'" />
                <x-api.field-row label="Authentication" value="Generic Credential Type → Header Auth" :mono="false" :copy="false" />
                <x-api.field-row label="Header Auth: Name" value="Authorization" />
                <x-api.field-row label="Header Auth: Value" :value="$tokenHint" />
                <x-api.field-row label="Send Body" value="On · Body Content Type: JSON · Specify Body: Using JSON" :mono="false" :copy="false" />
            </dl>
            <x-ui.code-block class="mt-3" :code="$offerJson" label="JSON body" />
            <p class="mt-2 text-xs text-ink-600">Saving the token as a Header Auth credential keeps it out of your workflow JSON when you share or export it.</p>
        </div>
        <div>
            <p class="text-sm font-semibold text-navy-900">Receive AffilStack events</p>
            <p class="mt-1 text-sm text-ink-600">Add a <strong>Webhook</strong> node with <em>HTTP Method</em> <code class="rounded bg-surface-muted px-1 font-mono text-xs">POST</code> and <em>Path</em> <code class="rounded bg-surface-muted px-1 font-mono text-xs">affilstack</code>. Activate the workflow and add its <strong>Production URL</strong> on the Webhooks tab. To check deliveries are genuine, compare the <code class="rounded bg-surface-muted px-1 font-mono text-xs">x-affilstack-signature</code> header with a <strong>Crypto</strong> node (HMAC, SHA256, hex) of the raw body using your endpoint's signing secret.</p>
        </div>
    </div>
</details>

{{-- Google Sheets --}}
<details id="recipe-sheets" class="group rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
    <summary class="flex cursor-pointer list-none items-center gap-3 px-5 py-4">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100"><x-heroicon-o-table-cells class="h-5 w-5" /></span>
        <span class="min-w-0 flex-1">
            <span class="block font-display font-semibold text-navy-900">Google Sheets (Apps Script)</span>
            <span class="block text-xs text-ink-600">Free, no third-party tool: a script that refreshes your CRM contacts into a sheet every hour.</span>
        </span>
        <x-heroicon-m-chevron-down class="h-5 w-5 text-ink-400 transition group-open:rotate-180" />
    </summary>
    <div class="space-y-4 border-t border-line px-5 py-5">
        <ol class="space-y-2 text-sm text-ink-900">
            <li class="flex gap-2"><span class="font-mono text-xs text-ink-400">1.</span> Open your sheet → <strong>Extensions → Apps Script</strong>. Replace the editor's contents with the script below and save.</li>
            <li class="flex gap-2"><span class="font-mono text-xs text-ink-400">2.</span> <strong>Project Settings (gear) → Script properties → Add script property</strong>: property <code class="rounded bg-surface-muted px-1 font-mono text-xs">AFFILSTACK_TOKEN</code>, value: your read-only token. (Never paste the token into the code itself.)</li>
            <li class="flex gap-2"><span class="font-mono text-xs text-ink-400">3.</span> Pick <code class="rounded bg-surface-muted px-1 font-mono text-xs">importAffilStackContacts</code> and click <strong>Run</strong>; approve Google's permission prompt once.</li>
            <li class="flex gap-2"><span class="font-mono text-xs text-ink-400">4.</span> <strong>Triggers (alarm clock) → Add Trigger</strong> → event source <em>Time-driven</em>, <em>Hour timer</em>, <em>Every hour</em>.</li>
        </ol>
        <x-ui.code-block :code="$appsScript" label="Code.gs" max-height="max-h-[28rem]" />
    </div>
</details>

{{-- Pabbly --}}
<details id="recipe-pabbly" class="group rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
    <summary class="flex cursor-pointer list-none items-center gap-3 px-5 py-4">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-violet-100"><x-heroicon-o-arrows-right-left class="h-5 w-5" /></span>
        <span class="min-w-0 flex-1">
            <span class="block font-display font-semibold text-navy-900">Pabbly Connect</span>
            <span class="block text-xs text-ink-600">Lifetime-deal favourite — Webhook by Pabbly and API by Pabbly.</span>
        </span>
        <x-heroicon-m-chevron-down class="h-5 w-5 text-ink-400 transition group-open:rotate-180" />
    </summary>
    <div class="space-y-5 border-t border-line px-5 py-5">
        <div>
            <p class="text-sm font-semibold text-navy-900">Receive AffilStack events</p>
            <p class="mt-1 text-sm text-ink-600">Trigger: <strong>Webhook by Pabbly</strong>. Copy the webhook URL, add it on the <a href="{{ $webhooksUrl }}" data-tab-link="webhooks" class="font-medium text-brand-600 underline">Webhooks tab</a>, then click <strong>Send test event</strong> while Pabbly is waiting for the webhook response.</p>
        </div>
        <div>
            <p class="text-sm font-semibold text-navy-900">Add a contact to AffilStack</p>
            <p class="mt-1 text-sm text-ink-600">Action: <strong>API by Pabbly</strong> → <strong>Execute API Request</strong>:</p>
            <dl class="mt-2 rounded-xl border border-line px-3">
                <x-api.field-row label="Action Event Method" value="POST" :copy="false" />
                <x-api.field-row label="API Endpoint URL" :value="$baseUrl.'/crm-contacts'" />
                <x-api.field-row label="Payload Type" value="JSON" :copy="false" />
                <x-api.field-row label="Authentication" value="No Auth (the token goes in Headers)" :mono="false" :copy="false" />
                <x-api.field-row label="Set Parameters" value="name, email, company, phone, notes → map from your trigger" :mono="false" :copy="false" />
                <x-api.field-row label="Headers: Authorization" :value="$tokenHint" />
                <x-api.field-row label="Headers: Accept" value="application/json" />
            </dl>
        </div>
    </div>
</details>

<x-ui.next-step :href="route('api-access.index', ['tab' => 'webhooks'])" action="Set up a webhook" data-tab-link="webhooks">
    Most recipes start with a webhook endpoint — add your tool's URL and send a test event so it learns the fields.
</x-ui.next-step>
