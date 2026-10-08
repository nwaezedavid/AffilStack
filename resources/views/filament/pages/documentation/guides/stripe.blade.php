<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <ul>
        <li><strong>A Stripe account</strong> at <x-docs.ext href="https://dashboard.stripe.com/register">dashboard.stripe.com</x-docs.ext>, activated for live payments.</li>
        <li><strong>A business in a Stripe-supported country.</strong> Stripe doesn't open accounts for businesses registered in Nigeria; many Nigerian founders use a US company (e.g. via Stripe Atlas). Check the <x-docs.ext href="https://stripe.com/global">supported countries list</x-docs.ext>. If that's not you, Flutterwave + PayPal cover dollar payments.</li>
        <li><strong>About 15 minutes.</strong> Fees: <x-docs.ext href="https://stripe.com/pricing">stripe.com/pricing</x-docs.ext>.</li>
    </ul>
    @include('filament.pages.documentation.guides._currency')
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Copy the API keys">
            <p>Stripe Dashboard → <x-docs.ui>Developers</x-docs.ui> → <x-docs.ui>API keys</x-docs.ui> (direct link: <x-docs.ext href="https://dashboard.stripe.com/apikeys">dashboard.stripe.com/apikeys</x-docs.ext>). Copy the <strong>Publishable key</strong> (<code>pk_live_…</code>) and click <x-docs.ui>Reveal live key</x-docs.ui> for the <strong>Secret key</strong> (<code>sk_live_…</code>). Use a sandbox's <code>pk_test_</code>/<code>sk_test_</code> keys for testing.</p>
        </x-docs.step>
        <x-docs.step title="Create the webhook (event destination)">
            <p>Open <x-docs.ext href="https://dashboard.stripe.com/webhooks">Workbench → Webhooks</x-docs.ext> → <x-docs.ui>Create an event destination</x-docs.ui> → <x-docs.ui>Your account</x-docs.ui>.</p>
            <x-docs.callout type="warning" title="Choose the Snapshot payload">
                Stripe asks for a payload format: pick <strong>Snapshot</strong>. AffilStack reads the full event object; "Thin" events would arrive but do nothing.
            </x-docs.callout>
            <p>Select exactly these six events:</p>
            <ul>
                <li><code>checkout.session.completed</code> — a payment or new subscription</li>
                <li><code>invoice.paid</code> — a monthly/yearly renewal succeeded</li>
                <li><code>invoice.payment_failed</code> — a renewal failed</li>
                <li><code>customer.subscription.deleted</code> — a subscription ended</li>
                <li><code>charge.refunded</code> — a refund</li>
                <li><code>charge.dispute.created</code> — a chargeback</li>
            </ul>
            <p><x-docs.ui>Continue</x-docs.ui> → destination type <x-docs.ui>Webhook endpoint</x-docs.ui> → <x-docs.ui>Continue</x-docs.ui> → Endpoint URL:</p>
            <x-docs.copy :value="$urls['webhook_stripe']" label="Endpoint URL" />
            <p>Create it. On the endpoint's page click <x-docs.ui>Reveal secret</x-docs.ui> and copy the <strong>signing secret</strong> (<code>whsec_…</code>). Test and live mode have different secrets.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="paste" title="Where to paste in AffilStack" icon="heroicon-o-clipboard">
    <p>Open <x-docs.admin-link route="filament.admin.pages.payment-gateway-settings">Billing → Payment Gateways</x-docs.admin-link> → <strong>Stripe</strong> card: <strong>Secret key</strong>, <strong>Publishable key</strong>, <strong>Webhook signing secret</strong> (<code>whsec_…</code>). Switch <x-docs.ui>Enabled</x-docs.ui> on → <x-docs.ui>Save changes</x-docs.ui> → <x-docs.ui>Verify credentials</x-docs.ui>.</p>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>The Stripe card shows "Verified"; the checklist card turns <strong>Working</strong>.</li>
        <li>With test keys, buy a plan using card <code>4242 4242 4242 4242</code>, any future date, any CVC. The plan activates.</li>
        <li>Workbench → Webhooks → your endpoint → <x-docs.ui>Event deliveries</x-docs.ui>: every delivery should be <code>200</code>. Use <x-docs.ui>Resend</x-docs.ui> to retry a failed one.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="Event deliveries fail with 401">
        <p>The <code>whsec_…</code> secret in AffilStack doesn't belong to this endpoint/mode. Each endpoint — and test vs live — has its own secret. Re-copy it from the endpoint you're looking at.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Renewals or refunds don't show in AffilStack">
        <p>The event isn't selected on the endpoint, or the destination uses "Thin" payloads. Edit the destination and add the missing events / recreate it with Snapshot.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Event deliveries fail with 3xx">
        <p>The URL redirects (e.g. http → https, or non-www → www). Use exactly the address above.</p>
    </x-docs.trouble>
</x-docs.section>
