<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <ul>
        <li><strong>A Meta Business portfolio</strong> (business.facebook.com) — the same one that owns your ad account.</li>
        <li><strong>About 10 minutes.</strong> The pixel is free; you pay only for ads.</li>
        <li>AffilStack loads the pixel and sends <code>PageView</code> events. Conversions (e.g. purchases) can be added later in Events Manager.</li>
    </ul>
    @include('filament.pages.documentation.guides._consent')
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Create the pixel (dataset)">
            <p>Open <x-docs.ext href="https://business.facebook.com/events_manager2">Meta Events Manager</x-docs.ext> → <x-docs.ui>Connect data</x-docs.ui> (the green <x-docs.ui>+</x-docs.ui>) → <x-docs.ui>Web</x-docs.ui> → name it <code>AffilStack</code> → <x-docs.ui>Create</x-docs.ui>. If asked how to connect, choose to install the code manually / skip partner integrations — AffilStack installs it for you.</p>
        </x-docs.step>
        <x-docs.step title="Copy the Pixel ID">
            <p>In Events Manager select the new dataset → <x-docs.ui>Settings</x-docs.ui>. Copy the <strong>Dataset ID / Pixel ID</strong> — a 15–16 digit number.</p>
        </x-docs.step>
        <x-docs.step title="Paste it into AffilStack">
            <p><x-docs.admin-link route="filament.admin.pages.seo-settings">SEO → SEO Settings</x-docs.admin-link> → <strong>Ad conversion pixels</strong> → <strong>Meta Pixel ID</strong> → <x-docs.ui>Save changes</x-docs.ui>. Paste only the number.</p>
        </x-docs.step>
        <x-docs.step title="(Recommended) Verify your domain with Meta">
            <p>business.facebook.com → <x-docs.ui>Settings</x-docs.ui> → <x-docs.ui>Brand safety and suitability</x-docs.ui> → <x-docs.ui>Domains</x-docs.ui> → <x-docs.ui>Add</x-docs.ui> <code>{{ $urls['root_domain'] }}</code> and use the DNS TXT method in hPanel. This lets you configure web events for iOS users.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>Events Manager → your dataset → <x-docs.ui>Test events</x-docs.ui> → enter <code>{{ $urls['site'] }}</code> → <x-docs.ui>Open website</x-docs.ui>. Click <strong>Accept all</strong> on the cookie banner — a <code>PageView</code> appears within seconds.</li>
        <li>Or install the <x-docs.ext href="https://chromewebstore.google.com/detail/meta-pixel-helper/fdgfkebogiimcoedlicjlajpkdmockpc">Meta Pixel Helper</x-docs.ext> Chrome extension.</li>
        <li>The checklist card shows "Set up" once the ID is saved (it can't be live-checked from the server).</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="No events in Test events">
        <p>You didn't accept marketing/advertising cookies, an ad blocker is on, or a browser privacy signal (Global Privacy Control / Do Not Track) is set — in all these cases the pixel correctly stays off.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Pixel Helper shows &quot;Invalid Pixel ID&quot;">
        <p>The field contains extra characters. Paste only the digits.</p>
    </x-docs.trouble>
</x-docs.section>
