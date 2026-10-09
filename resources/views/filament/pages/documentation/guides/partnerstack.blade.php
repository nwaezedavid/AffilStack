<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>Today AffilStack only <strong>stores and verifies</strong> your PartnerStack keys — no data syncs yet, and your built-in affiliate program works without it. Set it up only if you already run (or plan to run) a PartnerStack partner program.</p>
    <ul>
        <li><strong>A PartnerStack vendor account</strong> (paid; custom pricing).</li>
        <li><strong>5 minutes.</strong></li>
    </ul>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Find the API keys">
            <p>PartnerStack dashboard → <x-docs.ui>Settings</x-docs.ui> → <x-docs.ui>Integrations</x-docs.ui> / <x-docs.ui>API Keys</x-docs.ui>. Copy the <strong>Public key</strong> and <strong>Secret key</strong> of the <strong>production</strong> key pair.</p>
        </x-docs.step>
        <x-docs.step title="Paste them">
            <p><x-docs.admin-link route="filament.admin.pages.partner-stack-settings">Billing → PartnerStack</x-docs.admin-link> → <strong>Public key</strong>, <strong>Secret key</strong>, switch <x-docs.ui>Connection enabled</x-docs.ui> on → <x-docs.ui>Save changes</x-docs.ui> → <x-docs.ui>Verify connection</x-docs.ui>.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>Verify connection reports success; the checklist card turns <strong>Working</strong>.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="Verify connection: 401 / unauthorized">
        <p>The keys are from the test (sandbox) pair or were regenerated. Copy the current production keys.</p>
    </x-docs.trouble>
</x-docs.section>
