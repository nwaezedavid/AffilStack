<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <ul>
        <li><strong>An activated Paystack business account</strong> at <x-docs.ext href="https://dashboard.paystack.com/">dashboard.paystack.com</x-docs.ext>. Live keys only work after Paystack activates the account (business documents and settlement account).</li>
        <li><strong>A current USD → NGN rate</strong> — plan prices are in US dollars and are converted with the rate you type in AffilStack.</li>
        <li><strong>About 15 minutes.</strong> Fees: see <x-docs.ext href="https://paystack.com/pricing">paystack.com/pricing</x-docs.ext> (local cards currently 1.5% + ₦100, capped).</li>
    </ul>
    @include('filament.pages.documentation.guides._currency')
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Open API Keys & Webhooks">
            <p>Paystack dashboard → <x-docs.ui>Settings</x-docs.ui> → <x-docs.ui>API Keys &amp; Webhooks</x-docs.ui>. The page has two blocks: <strong>API Configuration – Test Mode</strong> and <strong>API Configuration – Live Mode</strong>. Use Test first.</p>
        </x-docs.step>
        <x-docs.step title="Copy the keys">
            <p>Copy the <strong>Secret key</strong> (<code>sk_test_…</code> / <code>sk_live_…</code> — click the eye icon and enter your password) and the <strong>Public key</strong> (<code>pk_…</code>).</p>
        </x-docs.step>
        <x-docs.step title="Set the webhook URL">
            <p>In the same block, paste this into <strong>Test Webhook URL</strong> (and later <strong>Live Webhook URL</strong>):</p>
            <x-docs.copy :value="$urls['webhook_paystack']" label="Webhook URL" />
            <p>Leave <strong>Callback URL</strong> empty — AffilStack sends its own return address with each payment. Click <x-docs.ui>Save changes</x-docs.ui>.</p>
            <p>Paystack sends every event to this URL; AffilStack acts on <code>charge.success</code> (payment), <code>refund.processed</code> (refund) and <code>charge.dispute.create</code> (chargeback) and ignores the rest. There's no separate webhook secret: Paystack signs each call with your <strong>secret key</strong> (<code>x-paystack-signature</code> header).</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="paste" title="Where to paste in AffilStack" icon="heroicon-o-clipboard">
    <x-docs.steps>
        <x-docs.step>
            <p>Open <x-docs.admin-link route="filament.admin.pages.payment-gateway-settings">Billing → Payment Gateways</x-docs.admin-link> → <strong>Paystack</strong> card.</p>
        </x-docs.step>
        <x-docs.step>
            <p>Paste <strong>Secret key</strong> and <strong>Public key</strong>. Enter today's <strong>USD → NGN exchange rate</strong> (e.g. the rate your bank charges). Switch <x-docs.ui>Enabled</x-docs.ui> on → <x-docs.ui>Save changes</x-docs.ui> → <x-docs.ui>Verify credentials</x-docs.ui>.</p>
        </x-docs.step>
    </x-docs.steps>
    <x-docs.callout type="warning" title="Keep the exchange rate current">
        There's no live rate feed. If the naira weakens and you don't update the rate, Nigerian customers pay less than the dollar price. Put a monthly reminder in your calendar.
    </x-docs.callout>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>The Paystack card shows "Verified" and the checklist card turns <strong>Working</strong>.</li>
        <li>From a Nigerian connection (or a VPN set to Nigeria), open checkout: "Pay in Naira (₦) — Paystack" appears first. Pay with a <x-docs.ext href="https://paystack.com/docs/payments/test-payments/">Paystack test card</x-docs.ext> in Test mode — the plan activates.</li>
        <li>Paystack dashboard → <x-docs.ui>Transactions</x-docs.ui> → open the payment → the webhook log should show <code>200</code>.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="Webhook returns 401 (Invalid webhook signature)">
        <p>The secret key saved in AffilStack is from the other mode (test vs live) than the webhook that fired, or it was regenerated in Paystack. Paste the current secret key of the same mode.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Customers outside Nigeria can't see Paystack">
        <p>By design — Paystack charges in Naira and is shown only to checkouts detected as Nigerian. Everyone else pays in dollars.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Transactions stopped after I generated new keys">
        <p>Paystack invalidates the old secret key. Paste the new one into AffilStack straight away.</p>
    </x-docs.trouble>
</x-docs.section>
