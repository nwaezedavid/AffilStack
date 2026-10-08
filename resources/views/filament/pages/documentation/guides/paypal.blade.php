<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <ul>
        <li><strong>A PayPal Business account</strong> (a personal account can be upgraded free) — Nigerian PayPal accounts can receive payments in many cases; confirm in your account's limits.</li>
        <li>Log in to the <x-docs.ext href="https://developer.paypal.com/dashboard/">PayPal Developer Dashboard</x-docs.ext> with the same account.</li>
        <li><strong>About 15 minutes.</strong> Fees: <x-docs.ext href="https://www.paypal.com/us/business/paypal-business-fees">PayPal business fees</x-docs.ext>.</li>
    </ul>
    @include('filament.pages.documentation.guides._currency')
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Switch to Live">
            <p>Developer Dashboard → <x-docs.ui>Apps &amp; Credentials</x-docs.ui>. Use the <x-docs.ui>Sandbox / Live</x-docs.ui> toggle at the top and pick <strong>Live</strong>.</p>
            <x-docs.callout type="info" title="Testing with Sandbox">
                AffilStack talks to live PayPal by default. To test with Sandbox credentials, add <code>PAYPAL_BASE_URL=https://api-m.sandbox.paypal.com</code> to the server .env first — and remove it again before going live.
            </x-docs.callout>
        </x-docs.step>
        <x-docs.step title="Create the app">
            <p><x-docs.ui>Create App</x-docs.ui> → name <code>AffilStack</code>, type <strong>Merchant</strong> → <x-docs.ui>Create App</x-docs.ui>. Copy the <strong>Client ID</strong> and the <strong>Secret</strong> (click <x-docs.ui>Show</x-docs.ui>).</p>
            <p>For affiliate payouts through PayPal, also make sure the <strong>Payouts</strong> feature is ticked under the app's features (PayPal may ask you to request access).</p>
        </x-docs.step>
        <x-docs.step title="Add the webhook">
            <p>On the app's page scroll to <strong>Webhooks</strong> → <x-docs.ui>Add Webhook</x-docs.ui>. Webhook URL:</p>
            <x-docs.copy :value="$urls['webhook_paypal']" label="Webhook URL" />
            <p>Tick these event types:</p>
            <ul>
                <li><code>CHECKOUT.ORDER.APPROVED</code> (Checkout order approved)</li>
                <li><code>PAYMENT.CAPTURE.COMPLETED</code> (Payment capture completed)</li>
                <li><code>PAYMENT.CAPTURE.DENIED</code> (Payment capture denied)</li>
                <li><code>PAYMENT.CAPTURE.REFUNDED</code> (Payment capture refunded)</li>
                <li><code>CUSTOMER.DISPUTE.CREATED</code> (Customer dispute created)</li>
            </ul>
            <p><x-docs.ui>Save</x-docs.ui>. Copy the <strong>Webhook ID</strong> shown next to the URL (a short code like <code>8PT59735JN…</code>).</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="paste" title="Where to paste in AffilStack" icon="heroicon-o-clipboard">
    <p><x-docs.admin-link route="filament.admin.pages.payment-gateway-settings">Billing → Payment Gateways</x-docs.admin-link> → <strong>PayPal</strong> card: <strong>Client ID</strong>, <strong>Client secret</strong>, <strong>Webhook ID</strong>. Switch <x-docs.ui>Enabled</x-docs.ui> on → <x-docs.ui>Save changes</x-docs.ui> → <x-docs.ui>Verify credentials</x-docs.ui>.</p>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>The PayPal card shows "Verified"; the checklist card turns <strong>Working</strong>.</li>
        <li>Buy the cheapest plan with a real PayPal account, then refund yourself from PayPal — both the payment and the refund should appear in AffilStack's Billing.</li>
        <li>Developer Dashboard → your app → <strong>Webhooks events</strong> lists each delivery and its status.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="Verify credentials: &quot;PayPal rejected the client credentials&quot;">
        <p>Sandbox credentials against live PayPal (or the reverse). Live credentials need no .env change; sandbox needs <code>PAYPAL_BASE_URL</code> as shown above.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Webhooks return 401">
        <p>The <strong>Webhook ID</strong> in AffilStack is missing or belongs to another app/mode. AffilStack asks PayPal to confirm every webhook using this ID — copy it again from the same app.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Payment taken but the plan isn't active">
        <p>Check the webhook list includes <code>CHECKOUT.ORDER.APPROVED</code> and <code>PAYMENT.CAPTURE.COMPLETED</code>, and look at the delivery status in the Developer Dashboard.</p>
    </x-docs.trouble>
</x-docs.section>
