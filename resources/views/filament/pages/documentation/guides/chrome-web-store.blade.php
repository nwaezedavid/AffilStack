<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>Until it's in the Chrome Web Store, customers install the AffilStack extension from a zip ("Load unpacked"). Publishing it gives them one-click install and automatic updates.</p>
    <ul>
        <li><strong>A Google account</strong> for the <x-docs.ext href="https://chrome.google.com/webstore/devconsole">Chrome Web Store Developer Dashboard</x-docs.ext> — a business account you'll keep. It needs 2-Step Verification.</li>
        <li><strong>US$5</strong> one-time registration fee (card).</li>
        <li><strong>Store images</strong>: at least one screenshot (1280×800 or 640×400) and a 440×280 small promo tile; the 128×128 icon is inside the package.</li>
        <li>Your <strong>privacy policy</strong>: <code>{{ $urls['privacy'] }}</code>.</li>
        <li><strong>45 minutes</strong>, then Google's review — usually 1–3 business days, longer for a first submission.</li>
    </ul>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Register as a Chrome Web Store developer">
            <p>Open the Developer Dashboard → accept the developer agreement → pay the US$5 fee. In <x-docs.ui>Account</x-docs.ui>, fill in the publisher name (<code>AffilStack</code>), verify the contact email, and complete the trader/non-trader declaration (you're a trader — you sell a service).</p>
        </x-docs.step>
        <x-docs.step title="Download the store package from AffilStack">
            <p>Log in to <strong>your own AffilStack dashboard</strong> as the owner (super-admin) → <a href="{{ $urls['extension'] }}" target="_blank" rel="noopener">Browser Extension</a> → in the gold "Owner only" box click <x-docs.ui>Chrome Web Store package</x-docs.ui>. This zip has the manifest at its root, your site icon (Admin → SEO → Site icon) as the extension icon, and <code>{{ $urls['api_base'] }}</code> as the default address. Don't upload the customer download zip — it's built differently.</p>
        </x-docs.step>
        <x-docs.step title="Upload it">
            <p>Developer Dashboard → <x-docs.ui>Add new item</x-docs.ui> (or, for an update, your item → <x-docs.ui>Package</x-docs.ui> → <x-docs.ui>Upload new package</x-docs.ui>) → choose the zip → <x-docs.ui>Upload</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step title="Store listing tab">
            <p>Copy the name, summary, description and category from the store texts below (they come from <code>resources/browser-extension/store/listing.md</code>, kept up to date with each extension version). Upload the screenshots and promo tile. Language: English.</p>
            @php($listing = is_file(resource_path('browser-extension/store/listing.md')) ? file_get_contents(resource_path('browser-extension/store/listing.md')) : null)
            @if ($listing)
                <details class="afs-g-trouble">
                    <summary>Store listing &amp; privacy texts (listing.md) — click to open</summary>
                    <div><x-docs.copy :value="$listing" label="Full text" /></div>
                </details>
            @endif
        </x-docs.step>
        <x-docs.step title="Privacy tab">
            <p>Fill in exactly what the "Privacy practices tab" part of the texts above says: the <strong>single purpose</strong>, a justification for <strong>each permission</strong> (activeTab, scripting, storage), "No" for remote code, the <strong>data usage</strong> boxes (personally identifiable information, website content and authentication information — yes; everything else — no), tick the three certification boxes, and the privacy policy URL. Make sure the privacy page mentions the browser extension: what it reads, only when clicked, sent only to the user's own account.</p>
            <x-docs.copy :value="$urls['privacy']" label="Privacy policy URL" />
        </x-docs.step>
        <x-docs.step title="Distribution and test instructions">
            <p><x-docs.ui>Distribution</x-docs.ui>: Free, Public, all regions. <x-docs.ui>Test instructions</x-docs.ui>: give the reviewer a test AffilStack login (create a customer account with an active plan) and the steps: sign in at {{ $urls['site'] }}, create an extension token on the Browser Extension page, paste it into the extension, then open a Google Maps business and LinkedIn profile.</p>
        </x-docs.step>
        <x-docs.step title="Submit for review">
            <p><x-docs.ui>Submit for review</x-docs.ui> → confirm. Leave automatic publishing on (or defer it and click Publish within 30 days of approval).</p>
        </x-docs.step>
        <x-docs.step title="Point AffilStack at the store listing">
            <p>Once published, copy the listing address (<code>https://chromewebstore.google.com/detail/…</code>) and add it to .env so the Browser Extension page shows "Add to Chrome" instead of the zip:</p>
            @include('filament.pages.documentation.guides._env', ['lines' => ['CHROME_EXTENSION_STORE_URL=https://chromewebstore.google.com/detail/your-extension-id']])
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>The Developer Dashboard shows the item as <strong>Published</strong>; the store page installs in a normal Chrome profile.</li>
        <li>The Browser Extension page in AffilStack shows the store button; the checklist card turns <strong>Working</strong>.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="Rejected: &quot;Excessive permissions&quot; / missing justification">
        <p>Every permission and host permission in the package needs a justification on the Privacy tab. Use the wording from listing.md and reply to the review email.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Rejected: &quot;Single purpose&quot; or misleading description">
        <p>Describe one clear purpose (turning Google Maps and LinkedIn pages into AffilStack CRM leads and messages) and don't promise features the extension doesn't have.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Upload error: manifest not found">
        <p>You uploaded the customer zip or a re-zipped folder. Use the "Chrome Web Store package" button — its manifest is at the root.</p>
    </x-docs.trouble>
    <x-docs.trouble title="&quot;Chrome Web Store package&quot; button isn't visible">
        <p>It's shown only to the owner account (super-admin role) on the customer dashboard's Browser Extension page.</p>
    </x-docs.trouble>
</x-docs.section>
