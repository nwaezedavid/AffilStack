<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <ul>
        <li><strong>A TikTok Ads Manager account</strong> at <x-docs.ext href="https://ads.tiktok.com/">ads.tiktok.com</x-docs.ext>.</li>
        <li><strong>About 10 minutes.</strong> Free; you pay only for ads. AffilStack loads the pixel and tracks page views.</li>
    </ul>
    @include('filament.pages.documentation.guides._consent')
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Create the pixel">
            <p>TikTok Ads Manager → <x-docs.ui>Tools</x-docs.ui> → <x-docs.ui>Events</x-docs.ui> (Events Manager) → <x-docs.ui>Connect data source</x-docs.ui> → <x-docs.ui>Web</x-docs.ui> → enter <code>{{ $urls['site'] }}</code> → choose <x-docs.ui>Manual setup</x-docs.ui> / TikTok Pixel → name it <code>AffilStack</code>. When asked how to install, choose the manual code option — you only need the ID.</p>
        </x-docs.step>
        <x-docs.step title="Copy the Pixel ID">
            <p>Open the pixel → <x-docs.ui>Details</x-docs.ui> / Settings. Copy the <strong>Pixel ID</strong> (about 20 capital letters and numbers, e.g. <code>C1A2B3…</code>).</p>
        </x-docs.step>
        <x-docs.step title="Paste it into AffilStack">
            <p><x-docs.admin-link route="filament.admin.pages.seo-settings">SEO → SEO Settings</x-docs.admin-link> → <strong>Ad conversion pixels</strong> → <strong>TikTok Pixel ID</strong> → <x-docs.ui>Save changes</x-docs.ui>.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>Install the <x-docs.ext href="https://ads.tiktok.com/help/article/tiktok-pixel-helper-2.0">TikTok Pixel Helper</x-docs.ext> Chrome extension, open the site, click <strong>Accept all</strong> on the cookie banner — the helper shows your pixel and a page view.</li>
        <li>Events Manager → your pixel → <x-docs.ui>Test events</x-docs.ui> / Diagnostics shows activity after a few minutes.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="The helper finds no pixel">
        <p>Cookies weren't accepted (or advertising was switched off in "Customise"), an ad blocker is on, or the ID has a typo.</p>
    </x-docs.trouble>
    <x-docs.trouble title="TikTok asks for a second (server-side) Events API connection">
        <p>That's optional. AffilStack's built-in integration is browser-only; ignore the Events API prompt unless you add a dedicated integration later.</p>
    </x-docs.trouble>
</x-docs.section>
