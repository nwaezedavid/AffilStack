<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <ul>
        <li><strong>A Microsoft, Google or Facebook account</strong> to sign in to <x-docs.ext href="https://www.bing.com/webmasters">Bing Webmaster Tools</x-docs.ext>.</li>
        <li><strong>The site live on its real domain</strong> (Bing fetches the homepage to verify).</li>
        <li><strong>About 10 minutes.</strong> Free. Bing's index also feeds DuckDuckGo, Yahoo and ChatGPT search.</li>
    </ul>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Add the site">
            <p>Sign in at bing.com/webmasters → <x-docs.ui>Add a site</x-docs.ui>. You can <x-docs.ui>Import</x-docs.ui> from Google Search Console (fastest, if Search Console is already verified — then skip to step 3), or add it manually with this URL:</p>
            <x-docs.copy :value="$urls['site'].'/'" label="Site URL" />
        </x-docs.step>
        <x-docs.step title="Copy the meta-tag verification code">
            <p>Choose the <x-docs.ui>HTML Meta Tag</x-docs.ui> option. Bing shows a tag like <code>&lt;meta name="msvalidate.01" content="ABC123…" /&gt;</code> — copy only the value inside <code>content="…"</code>. Don't click Verify yet.</p>
        </x-docs.step>
        <x-docs.step title="Generate an API key">
            <p>Top-right <x-docs.ui>Settings</x-docs.ui> (gear icon) → <x-docs.ui>API access</x-docs.ui> → <x-docs.ui>API Key</x-docs.ui> → <x-docs.ui>Generate API Key</x-docs.ui> → copy it.</p>
        </x-docs.step>
        <x-docs.step title="Paste both into AffilStack">
            <p><x-docs.admin-link route="filament.admin.pages.bing-webmaster-settings">Site → Bing Webmaster</x-docs.admin-link>: <strong>API key</strong>, <strong>Site verification code</strong>, switch <x-docs.ui>Enabled</x-docs.ui> on → <x-docs.ui>Save changes</x-docs.ui>. AffilStack now prints the verification tag on the homepage.</p>
        </x-docs.step>
        <x-docs.step title="Verify">
            <p>Back in Bing click <x-docs.ui>Verify</x-docs.ui>. Then in AffilStack click <x-docs.ui>Verify &amp; submit sitemap</x-docs.ui> — it submits:</p>
            <x-docs.copy :value="$urls['sitemap']" label="Sitemap" />
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>"Verify &amp; submit sitemap" reports success; the Bing card on the checklist and on Connections Health turns <strong>Working</strong>.</li>
        <li>In Bing Webmaster → <strong>Sitemaps</strong>, the sitemap appears; after a few days <strong>Site Explorer</strong> lists your pages.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="Bing says the meta tag wasn't found">
        <p>Save the code in AffilStack first, then verify. Paste only the content value (no quotes, no <code>&lt;meta</code>). If you use a CDN/cache, purge it.</p>
    </x-docs.trouble>
    <x-docs.trouble title="API key rejected / 401">
        <p>The key was regenerated in Bing (old keys stop working). Copy the current one again.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Sitemap submission fails">
        <p>The site must be verified in Bing first, under exactly the same address (https, with or without www).</p>
    </x-docs.trouble>
</x-docs.section>
