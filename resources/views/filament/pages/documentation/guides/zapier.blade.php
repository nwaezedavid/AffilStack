<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>The AffilStack Zapier app lives in the code at <code>integrations/zapier</code>. It lets a customer's Zaps react to <strong>New CRM Contact</strong> and <strong>New Offer Research Completed</strong>, and <strong>Create Offer</strong> / <strong>Create CRM Contact</strong> in AffilStack. Customers sign in with an API token from their <a href="{{ $urls['api_access'] }}" target="_blank" rel="noopener">API Access</a> page; each Zap appears there under Webhooks.</p>
    <ul>
        <li><strong>A free Zapier account</strong> for the developer platform (<x-docs.ext href="https://developer.zapier.com/">developer.zapier.com</x-docs.ext>).</li>
        <li><strong>A computer with Node.js 18+</strong> — the upload runs from a terminal, not from Hostinger. If that's not you, hand this guide to a developer; it takes them under an hour.</li>
        <li><strong>Cost:</strong> free to build and share privately; customers need their own Zapier plan for multi-step Zaps.</li>
    </ul>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Install and log in">
            <p>In a terminal, inside the <code>integrations/zapier</code> folder of the AffilStack code:</p>
            <x-docs.copy value="npm install" />
            <x-docs.copy value="npx zapier login" />
        </x-docs.step>
        <x-docs.step title="Register and push (first time)">
            <x-docs.copy value='npx zapier register "AffilStack"' />
            <x-docs.copy value="npx zapier push" />
            <p>For later versions only <code>npx zapier push</code> is needed.</p>
        </x-docs.step>
        <x-docs.step title="Make it reachable for customers">
            <p>In <x-docs.ext href="https://developer.zapier.com/">developer.zapier.com</x-docs.ext> → your AffilStack integration → <x-docs.ui>Sharing</x-docs.ui> (Manage → Sharing) → copy the <strong>public invite link</strong>. Anyone who opens it can use the integration while it stays private.</p>
        </x-docs.step>
        <x-docs.step title="Show the link in customers' dashboards">
            @include('filament.pages.documentation.guides._env', ['lines' => ['AFFILSTACK_ZAPIER_INVITE_URL=https://zapier.com/developer/public-invite/…']])
        </x-docs.step>
        <x-docs.step title="(Later) list it in Zapier's App Directory">
            <p>Once real customers use it, you can submit it for public listing from the same developer dashboard (Zapier reviews it and asks for help docs and an icon).</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>The API Access page shows the Zapier invite button; the checklist card turns <strong>Working</strong>.</li>
        <li>Create a Zap with trigger "AffilStack → New CRM Contact", connect it with an API token, add a contact in AffilStack — the Zap runs, and the subscription is listed under API Access → Webhooks.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="Connecting the account in Zapier fails (401)">
        <p>The API token is wrong, revoked or read-only (read-only tokens can trigger but not create). Create a new token on API Access.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Triggers never fire">
        <p>Check API Access → Webhooks for the Zap's subscription and its delivery log; the queue cron must be running for webhooks to be delivered.</p>
    </x-docs.trouble>
    <x-docs.trouble title="zapier push fails">
        <p>Use Node.js 18 or newer and run <code>npx zapier validate</code> to see the exact problem.</p>
    </x-docs.trouble>
</x-docs.section>
