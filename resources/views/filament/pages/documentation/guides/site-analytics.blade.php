<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>One Google connection lets AffilStack create a <strong>GA4 property</strong>, verify the site in <strong>Search Console</strong> and submit the sitemap, and create a <strong>Tag Manager container</strong> — then inject the right snippet sitewide, gated by cookie consent.</p>
    <ul>
        <li><strong>Google Sign-in set up</strong> (same Google app) — including redirect URI no. 4 below.</li>
        <li><strong>The site live on its real domain</strong> — Search Console verification fetches the live homepage.</li>
        <li><strong>A Google Analytics account</strong> — create one free at <x-docs.ext href="https://analytics.google.com/">analytics.google.com</x-docs.ext> (Start measuring → account name "AffilStack") if you don't have one. You can skip creating a property there; AffilStack creates it.</li>
        <li><strong>About 15 minutes.</strong> Free.</li>
    </ul>
    @include('filament.pages.documentation.guides._google-app')
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Check the redirect URI is registered">
            <p>Google Cloud Console → <x-docs.ui>Google Auth Platform</x-docs.ui> → <x-docs.ui>Clients</x-docs.ui> → your web client → Authorized redirect URIs must include:</p>
            <x-docs.copy :value="$urls['site_analytics_callback']" label="Site Analytics redirect URI" />
            <p class="afs-g-small">The Google Login settings page lists three redirect URIs; this fourth one is needed too.</p>
        </x-docs.step>
        <x-docs.step title="Enable the four Google APIs">
            <p>Google Cloud Console (same project) → <x-docs.ui>APIs &amp; Services</x-docs.ui> → <x-docs.ui>Library</x-docs.ui>. Search for each of these, open it and click <x-docs.ui>Enable</x-docs.ui>:</p>
            <ul>
                <li><strong>Google Analytics Admin API</strong></li>
                <li><strong>Google Search Console API</strong></li>
                <li><strong>Site Verification API</strong></li>
                <li><strong>Tag Manager API</strong></li>
            </ul>
        </x-docs.step>
        <x-docs.step title="Add the scopes to Data Access">
            <p><x-docs.ui>Google Auth Platform</x-docs.ui> → <x-docs.ui>Data Access</x-docs.ui> → <x-docs.ui>Add or remove scopes</x-docs.ui> → add <code>.../auth/analytics.edit</code>, <code>.../auth/webmasters</code>, <code>.../auth/siteverification.verify_only</code>, <code>.../auth/tagmanager.edit.containers</code>, <code>.../auth/tagmanager.publish</code> → <x-docs.ui>Update</x-docs.ui> → <x-docs.ui>Save</x-docs.ui>.</p>
            <x-docs.callout type="info" title="Only you use this connection">
                These are "sensitive" scopes, but only the admin connects them, once. Until Google verifies them (you'll do that anyway for Gmail/YouTube), Google shows "Google hasn't verified this app" — click <x-docs.ui>Advanced</x-docs.ui> → <x-docs.ui>Go to AffilStack (unsafe)</x-docs.ui>. It's your own app, so that's safe.
            </x-docs.callout>
        </x-docs.step>
        <x-docs.step title="Connect">
            <p>Open <x-docs.admin-link route="filament.admin.pages.google-site-analytics-settings">Site → Site Analytics</x-docs.admin-link> → <x-docs.ui>Connect with Google</x-docs.ui>. Choose the Google account that owns your Analytics account and tick <strong>all</strong> permission boxes on the consent screen.</p>
        </x-docs.step>
        <x-docs.step title="Google Analytics 4">
            <p>On the GA4 card click <x-docs.ui>Load Analytics accounts</x-docs.ui> → next to your account click <x-docs.ui>Create GA4 property</x-docs.ui>. The measurement ID (<code>G-…</code>) appears on the card and tracking goes live sitewide.</p>
        </x-docs.step>
        <x-docs.step title="Search Console">
            <p>Click <x-docs.ui>Verify Search Console</x-docs.ui>. AffilStack adds Google's verification tag to the homepage, asks Google to check it, then submits the sitemap:</p>
            <x-docs.copy :value="$urls['sitemap']" label="Sitemap" />
        </x-docs.step>
        <x-docs.step title="Tag Manager (optional)">
            <p>On the Tag Manager card click <x-docs.ui>Load Tag Manager accounts</x-docs.ui> → <x-docs.ui>Create container</x-docs.ui> next to your account. (Empty list? Create a free account at <x-docs.ext href="https://tagmanager.google.com/">tagmanager.google.com</x-docs.ext> first, then reload.)</p>
            <x-docs.callout type="warning" title="Add GA4 inside the new container straight away">
                AffilStack creates an <strong>empty</strong> container, and once it exists it replaces the bare GA4 snippet sitewide — so GA4 stops counting until you add it inside Tag Manager: tagmanager.google.com → your container → <x-docs.ui>Tags</x-docs.ui> → <x-docs.ui>New</x-docs.ui> → <x-docs.ui>Google tag</x-docs.ui> → Tag ID = your <code>G-…</code> measurement ID → Triggering: <x-docs.ui>Initialization – All Pages</x-docs.ui> → <x-docs.ui>Save</x-docs.ui> → <x-docs.ui>Submit</x-docs.ui> → <x-docs.ui>Publish</x-docs.ui>. Skip Tag Manager entirely if you don't need it.
            </x-docs.callout>
        </x-docs.step>
    </x-docs.steps>
    @include('filament.pages.documentation.guides._consent')
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>The Site Analytics page shows "Connected as …", the GA4 measurement ID, "Verified" for Search Console and the GTM ID. The checklist card shows <strong>Working</strong> when all three are set up.</li>
        <li>Open the site in a private window, click <strong>Accept all</strong> on the cookie banner, then check <strong>GA4 → Reports → Realtime</strong>: you appear within a minute.</li>
        <li>Search Console → <strong>Sitemaps</strong> shows the sitemap as "Success" after a day or two.</li>
        <li>Tag Manager → <strong>Preview</strong> → enter the site address — tags should fire after you accept cookies.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="redirect_uri_mismatch when clicking Connect with Google">
        <p>Redirect URI no. 4 (<code>{{ $urls['site_analytics_callback'] }}</code>) isn't on the OAuth client. Add it and wait a few minutes.</p>
    </x-docs.trouble>
    <x-docs.trouble title="403 &quot;… API has not been used in project … or it is disabled&quot;">
        <p>One of the four APIs in step 2 isn't enabled in the same Google Cloud project as the Client ID.</p>
    </x-docs.trouble>
    <x-docs.trouble title="&quot;Load Analytics accounts&quot; returns nothing">
        <p>The Google account you connected has no Analytics account (or no Editor rights on it). Create one at analytics.google.com, then reconnect.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Search Console verification fails">
        <p>The site must be publicly reachable on its final domain. If it still fails (e.g. a permissions error), verify manually: Search Console → Add property → URL prefix <code>{{ $urls['site'] }}/</code> → <strong>HTML tag</strong> method → copy only the <code>content="…"</code> value into <x-docs.admin-link route="filament.admin.pages.seo-settings">SEO Settings → Google Search Console verification code</x-docs.admin-link> → Save → back in Search Console click Verify. Then submit the sitemap there.</p>
    </x-docs.trouble>
    <x-docs.trouble title="The connection stops working after about a week">
        <p>Your Google app is still in "Testing" — Google expires test-mode access after 7 days. Publish it (Google Auth Platform → Audience → Publish app) and reconnect.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Realtime shows nobody">
        <p>Did you accept cookies? Is an ad blocker on? With a Tag Manager container connected, GA4 only fires if a GA4/Google tag is published inside the container.</p>
    </x-docs.trouble>
</x-docs.section>
