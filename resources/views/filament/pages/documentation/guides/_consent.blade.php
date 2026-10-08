{{-- Shared: how cookie consent gates GA4/GTM and the ad pixels. --}}
<x-docs.callout type="info" title="How cookie consent controls this tag">
    <p>AffilStack's cookie banner (<x-docs.admin-link route="filament.admin.analytics.pages.cookie-consent-settings">Analytics → Cookie Consent</x-docs.admin-link>) decides when tracking tags load:</p>
    <ul>
        <li><strong>Until a visitor chooses</strong>, Google Analytics/Tag Manager and the Meta and TikTok pixels stay off (Google Consent Mode v2 starts as "denied").</li>
        <li><strong>Google Analytics / Tag Manager</strong> load once the visitor allows analytics <em>or</em> advertising — Consent Mode tells Google exactly which. (If you chose "Advanced Consent Mode", Google loads straight away but only sends cookieless pings until consent.)</li>
        <li><strong>The Meta and TikTok pixels</strong> load only once the visitor allows advertising.</li>
        <li><strong>Reject all</strong>, Global Privacy Control or Do Not Track: the tags never load for that visitor.</li>
        <li>If you switch the banner <strong>off</strong>, no analytics or advertising tag ever loads.</li>
    </ul>
    <p>So when you test, <strong>accept cookies first</strong> (or use a private window and click "Accept all") — otherwise the tag correctly won't fire.</p>
</x-docs.callout>
