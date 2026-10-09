<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p><strong>Brain</strong> is AffilStack's ads agent. With a Claude API key it <strong>drafts</strong> Meta ad campaigns for review. With a <strong>Meta Ads MCP server</strong> connected as well, Claude can call that server's tools to actually <strong>launch and optimize</strong> campaigns — AffilStack itself never talks to Meta's ads API directly.</p>
    <ul>
        <li><strong>A Claude API key</strong> from the Claude Console — see <x-docs.guide-link key="ai-engine">AI Engine</x-docs.guide-link> for creating one. You can reuse the same key. A Claude Pro/Max subscription can't be used (the AI Engine guide explains why).</li>
        <li><strong>For launching:</strong> a Meta ad account you manage in a Business portfolio, and a remote MCP server for Meta Ads that accepts a bearer token (options below).</li>
        <li><strong>Time:</strong> 20 minutes for Claude; about 30 minutes for the MCP server.</li>
    </ul>
    <x-docs.callout type="cost" title="Costs">
        Claude API usage is pay-as-you-go (<x-docs.ext href="https://www.anthropic.com/pricing#api">pricing</x-docs.ext>); campaign launches spend real ad budget on your Meta ad account; hosted MCP services may charge a subscription.
    </x-docs.callout>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <h3>A. Claude (drafting)</h3>
    <x-docs.steps>
        <x-docs.step title="Get the API key">
            <p><x-docs.ext href="https://platform.claude.com/">platform.claude.com</x-docs.ext> → <x-docs.ui>API Keys</x-docs.ui> → <x-docs.ui>Create Key</x-docs.ui> (or reuse the AI Engine key). Make sure the account has credit.</p>
        </x-docs.step>
        <x-docs.step title="Paste it">
            <p><x-docs.admin-link route="filament.admin.pages.brain-agent-settings">AI Agents → Brain Settings</x-docs.admin-link> → <strong>Claude (Anthropic)</strong> → <strong>Anthropic API key</strong>. Leave <strong>Model</strong> blank for the default → <x-docs.ui>Save changes</x-docs.ui> → <x-docs.ui>Verify Claude connection</x-docs.ui>.</p>
        </x-docs.step>
    </x-docs.steps>

    <h3>B. Meta Ads MCP server (launching &amp; optimizing)</h3>
    <p>Brain uses Anthropic's <strong>MCP connector</strong>: Claude connects to a remote MCP server over HTTPS, sending the token you save as <code>Authorization: Bearer …</code>. So you need a Meta Ads MCP server reachable on the internet that accepts a bearer token. Options (October 2026):</p>
    <div class="afs-g-table-wrap">
        <table class="afs-g-table">
            <thead><tr><th>Option</th><th>Server URL</th><th>Token</th><th>Notes</th></tr></thead>
            <tbody>
                <tr><td><strong>Pipeboard (hosted)</strong></td><td><code>https://meta-ads.mcp.pipeboard.co/</code></td><td>Create at <x-docs.ext href="https://pipeboard.co/api-tokens">pipeboard.co/api-tokens</x-docs.ext> after connecting your Meta account in Pipeboard</td><td>Third-party service — read its pricing and privacy terms; it gets access to your ad accounts.</td></tr>
                <tr><td><strong>Self-hosted open source</strong></td><td>Your own HTTPS address</td><td>A secret you configure + a Meta System User token</td><td><x-docs.ext href="https://github.com/pipeboard-co/meta-ads-mcp">meta-ads-mcp</x-docs.ext> can run on a small VPS (not on Hostinger shared/cloud hosting). Developer task.</td></tr>
                <tr><td><strong>Meta's official Ads MCP</strong></td><td><code>https://mcp.facebook.com/ads</code></td><td>Interactive Meta Business login</td><td>Beta since April 2026, built for Claude Desktop/ChatGPT. It signs in through a browser and doesn't hand out a static token, so it can't currently be pasted here.</td></tr>
            </tbody>
        </table>
    </div>
    <x-docs.steps>
        <x-docs.step title="Set up the server and get its URL and token">
            <p>Follow the option you chose. Give it access only to the ad account(s) Brain should manage.</p>
        </x-docs.step>
        <x-docs.step title="Paste it">
            <p>Brain Settings → <strong>Meta Ads MCP server</strong> → <strong>MCP server URL</strong> and <strong>Authorization token</strong> → <x-docs.ui>Save changes</x-docs.ui> → <x-docs.ui>Verify Meta Ads MCP connection</x-docs.ui> (Claude connects and lists the server's tools).</p>
        </x-docs.step>
        <x-docs.step title="Allow live launches">
            <p>Only when both checks pass and you're happy with Brain's drafts, turn on <x-docs.ui>Brain can launch and optimize live campaigns</x-docs.ui> at the top of Brain Settings. While it's off, "Approve &amp; Launch" stays disabled.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>Both verify buttons report success; the checklist card shows <strong>Working</strong> (Claude and MCP each count — "1 of 2 working" means only one is set up).</li>
        <li>Open the Brain dashboard and draft a campaign. With launching allowed, approve a small test campaign with a tiny budget and confirm it appears in Meta Ads Manager.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="Verify Claude connection fails">
        <p>See the AI Engine guide's troubleshooting — usually a mistyped key or no credit.</p>
    </x-docs.trouble>
    <x-docs.trouble title="MCP verification: 401 / unauthorized">
        <p>The token is wrong or expired. Generate a new one on the MCP provider and paste it. Paste the token only — not the word "Bearer".</p>
    </x-docs.trouble>
    <x-docs.trouble title="MCP verification: connection error / timeout">
        <p>The URL must be public HTTPS (Anthropic's servers connect to it, not your browser) and speak MCP over HTTP. Localhost or private addresses never work.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Campaign launch fails with a Meta permission error">
        <p>The MCP server's Meta login doesn't have access to that ad account, or the ad account has a payment problem. Check Meta Business Settings → Ad accounts.</p>
    </x-docs.trouble>
</x-docs.section>
