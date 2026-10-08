<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <ul>
        <li><strong>A Flutterwave business account</strong> at <x-docs.ext href="https://app.flutterwave.com/">app.flutterwave.com</x-docs.ext>. Live payments only work after Flutterwave approves your business (KYC documents, settlement bank account) — start this early, it can take a few days.</li>
        <li><strong>About 15 minutes</strong> once approved. No monthly fee; Flutterwave takes a percentage of each payment (see <x-docs.ext href="https://flutterwave.com/us/pricing">pricing</x-docs.ext>).</li>
    </ul>
    @include('filament.pages.documentation.guides._currency')
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Choose Test or Live mode">
            <p>In the Flutterwave dashboard, use the <x-docs.ui>Test mode / Live mode</x-docs.ui> switch at the top. Test keys contain <code>_TEST</code> and only accept Flutterwave's test cards; live keys charge real cards. Do a full test first, then repeat steps 2–4 in Live mode.</p>
        </x-docs.step>
        <x-docs.step title="Copy your API keys">
            <p><x-docs.ui>Settings</x-docs.ui> → <x-docs.ui>API Keys</x-docs.ui>. Copy the <strong>Secret key</strong> (<code>FLWSECK-…</code>) and the <strong>Public key</strong> (<code>FLWPUBK-…</code>). You don't need the encryption key.</p>
        </x-docs.step>
        <x-docs.step title="Create the webhook">
            <p><x-docs.ui>Settings</x-docs.ui> → <x-docs.ui>Webhooks</x-docs.ui>. Paste this as the URL:</p>
            <x-docs.copy :value="$urls['webhook_flutterwave']" label="Webhook URL" />
            <p>In <strong>Secret hash</strong>, type a long random password you make up (e.g. 32+ letters and numbers) — write it down, you'll paste the same value into AffilStack. Flutterwave sends it back in the <code>verif-hash</code> header so AffilStack knows the call is genuine.</p>
            <p>Tick all the checkboxes (webhook retries, failed-transaction webhooks, JSON responses) as Flutterwave's own docs recommend → <x-docs.ui>Save</x-docs.ui>.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="paste" title="Where to paste in AffilStack" icon="heroicon-o-clipboard">
    <x-docs.steps>
        <x-docs.step>
            <p>Open <x-docs.admin-link route="filament.admin.pages.payment-gateway-settings">Billing → Payment Gateways</x-docs.admin-link> → <strong>Flutterwave</strong> card.</p>
        </x-docs.step>
        <x-docs.step>
            <p><strong>Secret key</strong> → Secret key field. <strong>Public key</strong> → Public key. Your made-up secret hash → <strong>Webhook secret hash</strong> (not the API secret key!). Switch <x-docs.ui>Enabled</x-docs.ui> on → <x-docs.ui>Save changes</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step>
            <p>Click <x-docs.ui>Verify credentials</x-docs.ui> on the Flutterwave card. Not sure what an error means? <x-docs.ui>Explain with AI</x-docs.ui> translates it.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>The Flutterwave card shows a green "Verified" badge, and the checklist card turns <strong>Working</strong>.</li>
        <li>In <strong>Test mode</strong>: sign up for a plan from a second account and pay with a <x-docs.ext href="https://developer.flutterwave.com/docs/test-cards">Flutterwave test card</x-docs.ext>. The plan should activate immediately and the transaction appear under Billing.</li>
        <li>Flutterwave dashboard → Settings → Webhooks shows recent deliveries; each should be <code>200</code>.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="Webhook deliveries fail with 401 (Invalid webhook signature)">
        <p>The <strong>Secret hash</strong> in Flutterwave and the <strong>Webhook secret hash</strong> in AffilStack differ (or one is empty). Paste the exact same value in both, with no spaces. Payments still activate through the browser redirect, but webhooks are the safety net when a customer closes the tab — fix this.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Verify credentials fails">
        <p>You may be mixing Test and Live keys, or copied a v4 client ID instead of the v3 secret key. Re-copy the secret key starting with <code>FLWSECK</code>.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Customers in Nigeria don't see Flutterwave">
        <p>They do — Flutterwave is a dollar gateway shown to everyone. If Paystack is on, Nigerians see "Pay in Naira" first and the dollar options below it.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Payment succeeded at Flutterwave but the plan didn't activate">
        <p>AffilStack checks the amount and currency against the order. Look at the transaction in Billing for the reason; check the webhook delivery log in Flutterwave for a non-200 status.</p>
    </x-docs.trouble>
</x-docs.section>
