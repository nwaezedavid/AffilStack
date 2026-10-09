<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>When a customer clicks <strong>Generate video</strong> on a UGC script, AffilStack sends it to HeyGen on <strong>your</strong> HeyGen account, downloads the finished avatar video to your server and charges the customer AffilStack credits.</p>
    <ul>
        <li><strong>A HeyGen account</strong> at <x-docs.ext href="https://app.heygen.com/">app.heygen.com</x-docs.ext> with <strong>API credit</strong>. API usage is billed separately from HeyGen's web-app subscription — see <x-docs.ext href="https://www.heygen.com/api-pricing">HeyGen API pricing</x-docs.ext> and make sure your credit price per video covers it.</li>
        <li><strong>About 10 minutes.</strong></li>
    </ul>
    <x-docs.callout type="tip" title="Funding Watch keeps an eye on the balance">
        AffilStack's Funding Watch checks HeyGen's remaining credit daily and alerts you before it runs out (System → Funding Watch).
    </x-docs.callout>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Buy API credit">
            <p>In HeyGen open the API section (<x-docs.ext href="https://app.heygen.com/settings/api">app.heygen.com/settings/api</x-docs.ext>, or Settings → API) and choose an API plan / add credit.</p>
        </x-docs.step>
        <x-docs.step title="Copy the API key">
            <p>On the same API page, copy (or generate) the <strong>API key</strong> / API token. Treat it like a password.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="paste" title="Where to paste in AffilStack" icon="heroicon-o-clipboard">
    <p><x-docs.admin-link route="filament.admin.pages.ugc-video-settings">Content → UGC Video Settings</x-docs.admin-link> → <strong>HeyGen API key</strong>, turn on <x-docs.ui>Users can generate UGC videos</x-docs.ui> → <x-docs.ui>Save changes</x-docs.ui> → <x-docs.ui>Verify connection</x-docs.ui>.</p>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>The HeyGen section shows "✓ Verified …" and the checklist card turns <strong>Working</strong>.</li>
        <li>From a customer account with credits, generate one UGC video — it renders in a few minutes and plays in the dashboard.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="Verify connection: 401 / invalid API key">
        <p>The key was copied incompletely or regenerated in HeyGen. Paste the current one.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Videos fail with &quot;insufficient credit&quot; / quota">
        <p>Your HeyGen API balance is empty. Top up; Funding Watch's threshold can warn you earlier.</p>
    </x-docs.trouble>
    <x-docs.trouble title="&quot;HeyGen didn't finish this video in time&quot;">
        <p>HeyGen was slow; the video may still complete on their side. Check the HeyGen dashboard, then let the customer retry.</p>
    </x-docs.trouble>
</x-docs.section>
