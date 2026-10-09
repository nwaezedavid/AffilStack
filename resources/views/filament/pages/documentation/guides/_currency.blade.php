{{-- Shared: how AffilStack decides which gateways and currency a customer sees. --}}
<x-docs.callout type="info" title="Which gateway each customer sees (currency rules)">
    <ul>
        <li><strong>Everyone, everywhere</strong> (Nigeria included) can pay in <strong>US dollars</strong> through any enabled dollar gateway: Flutterwave, Stripe, PayPal.</li>
        <li><strong>Paystack charges in Naira (₦)</strong> and is offered <strong>only</strong> to checkouts detected as Nigerian — listed first — and only while Paystack is switched on. Plan prices are in USD and multiplied by the <strong>USD → NGN exchange rate</strong> you set on the Paystack card (there's no live rate feed — update it yourself).</li>
        <li>A customer can only pay with a gateway whose <strong>Enabled</strong> toggle is on. Keep at least one dollar gateway enabled at all times.</li>
    </ul>
</x-docs.callout>
