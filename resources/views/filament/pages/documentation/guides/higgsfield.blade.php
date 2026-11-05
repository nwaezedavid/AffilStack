<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <ul>
        <li><strong>A Higgsfield developer account</strong> at console.higgsfield.ai. The developer API is separate from a Higgsfield website plan: website credits don't work for the API.</li>
        <li><strong>A card</strong> for the API balance. The minimum top-up is US$5. A top-up of US$25 or more raises how many videos can render at the same time (from 2 to 10).</li>
        <li><strong>About 10 minutes.</strong></li>
    </ul>
    <x-docs.callout type="cost" title="What it costs you">
        <p>Higgsfield bills each finished video from your API balance, per second of video, depending on the model and resolution. Failed or blocked videos aren't charged. Customers pay you with the credits you set per style. After connecting, press <x-docs.ui>Estimate Higgsfield cost</x-docs.ui> to see your cost per video and set the credit prices above it.</p>
    </x-docs.callout>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Create the API key">
            <p>Sign in at console.higgsfield.ai → <x-docs.ui>API keys</x-docs.ui> → <x-docs.ui>Create key</x-docs.ui>. Copy the <strong>Key ID</strong> and the <strong>Secret</strong> (the secret is shown only once).</p>
        </x-docs.step>
        <x-docs.step title="Add funds">
            <p>In the console → <x-docs.ui>Billing</x-docs.ui> → top up your balance (US$25 recommended for launch).</p>
        </x-docs.step>
        <x-docs.step title="Paste the key in AffilStack">
            <p>Admin → <x-docs.ui>Content</x-docs.ui> → <x-docs.ui>UGC Video Settings</x-docs.ui> → <strong>Higgsfield</strong> section: paste the Key ID and Secret, switch on <strong>Use Higgsfield for product videos</strong>, then <x-docs.ui>Save changes</x-docs.ui> and <x-docs.ui>Verify Higgsfield</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step title="(Optional) Instant delivery with a webhook">
            <p>In the console, add a webhook pointing to the address shown under <strong>Webhook signing secret</strong> on the settings page, copy its signing secret (starts with <code>whsec_</code>) and paste it there. Without it, AffilStack checks every 30 seconds instead.</p>
        </x-docs.step>
        <x-docs.step title="Set the styles and prices">
            <p>In <strong>One-click video styles</strong>, switch each style on or off and set its credit price and length. Press <x-docs.ui>Estimate Higgsfield cost</x-docs.ui> to compare your cost with the price.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ol>
        <li><x-docs.ui>Verify Higgsfield</x-docs.ui> says <strong>Higgsfield connected</strong>.</li>
        <li>As a customer, open a researched product → <strong>UGC video</strong> → choose <strong>Product showcase</strong> → <x-docs.ui>Create video</x-docs.ui>. Within about 2–10 minutes the finished video appears with its Publish buttons.</li>
    </ol>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <ul>
        <li><strong>"Higgsfield did not accept this API key"</strong> — the Key ID or Secret is wrong, or the key was deleted. Create a new key and paste both again.</li>
        <li><strong>"Your Higgsfield API balance is too low"</strong> — add funds in the console.</li>
        <li><strong>Customers see "We couldn't find a picture of this product"</strong> — the product's website has no share image. The customer can upload a product photo under <strong>Customise</strong>.</li>
        <li><strong>A video was flagged as unsafe</strong> — Higgsfield's moderation stopped it; the customer isn't charged. Another photo or style usually works.</li>
        <li><strong>Product styles show "Coming soon"</strong> — Higgsfield isn't verified or is switched off, or the style is switched off.</li>
    </ul>
</x-docs.section>
