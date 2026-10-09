<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <ul>
        <li><strong>A Google Cloud project</strong> — the AffilStack project from Google Sign-in is fine.</li>
        <li><strong>A billing account with a card.</strong> Google Maps Platform won't answer without billing, even inside the free allowance.</li>
        <li><strong>About 15 minutes.</strong></li>
    </ul>
    <x-docs.callout type="cost" title="What it costs (Places API (New), October 2026)">
        <p>Each customer <strong>search</strong> asks for name, address, category and star rating — billed as <strong>Text Search Enterprise</strong>: first <strong>1,000 a month free</strong>, then about <strong>US$35 per 1,000</strong>. Each business a customer <strong>imports</strong> fetches phone + website — <strong>Place Details Enterprise</strong>: 1,000 free a month, then about <strong>US$20 per 1,000</strong>. So 3,000 searches + 2,000 imports in a month ≈ US$70 + US$20. Price your credits accordingly and set a budget alert (step 5). Source: <x-docs.ext href="https://developers.google.com/maps/billing-and-pricing/pricing">Google Maps Platform pricing</x-docs.ext>.</p>
    </x-docs.callout>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Turn on billing">
            <p>Google Cloud Console → menu <x-docs.ui>☰</x-docs.ui> → <x-docs.ui>Billing</x-docs.ui> → <x-docs.ui>Link a billing account</x-docs.ui> (or <x-docs.ui>Create account</x-docs.ui> and add a card) for the AffilStack project.</p>
        </x-docs.step>
        <x-docs.step title="Enable Places API (New)">
            <p><x-docs.ui>APIs &amp; Services</x-docs.ui> → <x-docs.ui>Library</x-docs.ui> → search <strong>Places API (New)</strong> → <x-docs.ui>Enable</x-docs.ui>. Choose the one marked <strong>(New)</strong>; the old "Places API" can't be enabled on projects created after March 2025 and AffilStack doesn't use it.</p>
        </x-docs.step>
        <x-docs.step title="Create the API key">
            <p><x-docs.ui>APIs &amp; Services</x-docs.ui> → <x-docs.ui>Credentials</x-docs.ui> → <x-docs.ui>Create credentials</x-docs.ui> → <x-docs.ui>API key</x-docs.ui>. Copy it (starts with <code>AIza</code>).</p>
        </x-docs.step>
        <x-docs.step title="Restrict the key (important)">
            <p>Click the key's name to edit it. Name: <code>AffilStack Places (server)</code>.</p>
            <ul>
                <li><strong>API restrictions</strong> → <x-docs.ui>Restrict key</x-docs.ui> → tick only <strong>Places API (New)</strong>. Always do this — a leaked key then can't be used for other paid Google APIs.</li>
                <li><strong>Application restrictions</strong> → <x-docs.ui>IP addresses</x-docs.ui> → your server's outgoing IP. Optional: on shared/cloud hosting the outgoing IP can differ from the IP hPanel shows. If searches fail with "not authorized" after adding it, set this back to <x-docs.ui>None</x-docs.ui>.</li>
            </ul>
            <p><x-docs.ui>Save</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step title="Set a budget alert and a daily cap">
            <p><x-docs.ui>Billing</x-docs.ui> → <x-docs.ui>Budgets &amp; alerts</x-docs.ui> → <x-docs.ui>Create budget</x-docs.ui> (e.g. US$50/month, alerts at 50/90/100%). Then <x-docs.ui>APIs &amp; Services</x-docs.ui> → <strong>Places API (New)</strong> → <x-docs.ui>Quotas &amp; System Limits</x-docs.ui> → lower the per-day request limits to a number you're happy to pay for. A budget only warns; a quota actually stops spending.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="paste" title="Where to paste in AffilStack" icon="heroicon-o-clipboard">
    <p>The key lives in the server <code>.env</code> file:</p>
    @include('filament.pages.documentation.guides._env', ['lines' => ['GOOGLE_PLACES_API_KEY=AIza-your-key']])
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>The checklist card shows "Set up" once the key is in .env.</li>
        <li>From a customer account open <strong>Local Leads</strong> and search e.g. "dentists in Lagos" — results appear. Import one; its phone and website fill in.</li>
        <li>Cloud Console → <x-docs.ui>APIs &amp; Services</x-docs.ui> → Places API (New) → <x-docs.ui>Metrics</x-docs.ui> shows the calls.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="&quot;Google Maps isn't configured yet&quot;">
        <p><code>GOOGLE_PLACES_API_KEY</code> is missing from .env, or the config cache wasn't cleared after adding it.</p>
    </x-docs.trouble>
    <x-docs.trouble title="403 PERMISSION_DENIED — &quot;Places API (New) has not been used in project … or it is disabled&quot;">
        <p>Enable <strong>Places API (New)</strong> in the same project the key belongs to (not the legacy Places API).</p>
    </x-docs.trouble>
    <x-docs.trouble title="403 — &quot;This API key is not authorized to use this service&quot; / &quot;requests from this IP are blocked&quot;">
        <p>The key's API restriction doesn't include Places API (New), or the IP restriction doesn't match the server's real outgoing IP. Fix the restriction (or remove the IP one).</p>
    </x-docs.trouble>
    <x-docs.trouble title="Billing must be enabled">
        <p>Link a billing account to the project (step 1).</p>
    </x-docs.trouble>
    <x-docs.trouble title="429 / quota exceeded">
        <p>You hit the daily cap you set (or Google's per-minute limit). Raise the quota if the spend is expected; customers see a friendly "try again later" meanwhile.</p>
    </x-docs.trouble>
</x-docs.section>
