<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>Customers connect TikTok on <a href="{{ $urls['connected_accounts'] }}" target="_blank" rel="noopener">Connected Accounts</a> (Login Kit), and AffilStack posts their finished UGC videos with the <strong>Content Posting API — Direct Post</strong>. Scopes: <code>user.info.basic</code> and <code>video.publish</code>. TikTok fetches each video from your site (<code>{{ $urls['site'] }}/storage/…</code>).</p>
    <ul>
        <li><strong>A TikTok account</strong> to log in to <x-docs.ext href="https://developers.tiktok.com/">developers.tiktok.com</x-docs.ext>, ideally registered as an organization (business details).</li>
        <li><strong>Public Terms and Privacy pages</strong>, an app icon (1024×1024 recommended), and a short <strong>demo video</strong> of the connect-and-post flow for review.</li>
        <li><strong>Time:</strong> about 45 minutes, then two reviews — app review (days) and the Content Posting <strong>audit</strong> (days to weeks).</li>
    </ul>
    <x-docs.callout type="warning" title="Until TikTok audits the app">
        <p>TikTok's rules for unaudited apps: only <strong>5 people per 24 hours</strong> can post, their accounts must be <strong>private</strong>, and posts can only be "Only me" (<code>SELF_ONLY</code>). AffilStack posts publicly, so it keeps publishing switched to "download and post manually" until you mark the audit <strong>Approved</strong> in AffilStack.</p>
    </x-docs.callout>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Create the app">
            <p>developers.tiktok.com → log in → <x-docs.ui>Manage apps</x-docs.ui> → <x-docs.ui>Connect an app</x-docs.ui> → choose the organization/individual owner → name <code>AffilStack</code>.</p>
        </x-docs.step>
        <x-docs.step title="Fill in the app details">
            <p>Upload the icon, pick a category, write a one-paragraph description ("AffilStack helps affiliate marketers create short videos and post them to their own TikTok account"). Add:</p>
            <x-docs.copy :value="$urls['terms']" label="Terms of Service URL" />
            <x-docs.copy :value="$urls['privacy']" label="Privacy Policy URL" />
            <p>Platforms: tick <x-docs.ui>Web</x-docs.ui> and enter <code>{{ $urls['site'] }}</code>.</p>
        </x-docs.step>
        <x-docs.step title="Add Login Kit and the redirect URI">
            <p><x-docs.ui>Add products</x-docs.ui> → <x-docs.ui>Login Kit</x-docs.ui>. In Login Kit's settings, Redirect URI (Web):</p>
            <x-docs.copy :value="$urls['tiktok_callback']" label="Redirect URI" />
        </x-docs.step>
        <x-docs.step title="Add the Content Posting API with Direct Post">
            <p><x-docs.ui>Add products</x-docs.ui> → <x-docs.ui>Content Posting API</x-docs.ui> → switch on <x-docs.ui>Direct Post</x-docs.ui>. Under <x-docs.ui>Scopes</x-docs.ui> make sure <code>user.info.basic</code> and <code>video.publish</code> are added.</p>
        </x-docs.step>
        <x-docs.step title="Verify your domain for PULL_FROM_URL">
            <p>Because TikTok downloads each video from your server, it must know you own the address. In the Content Posting API section open <x-docs.ui>URL properties</x-docs.ui> / verify domain → add the domain <code>{{ $urls['host'] }}</code> (or the URL prefix below) → TikTok gives you a TXT record → add it in hPanel → <x-docs.ui>Domains</x-docs.ui> → {{ $urls['root_domain'] }} → <x-docs.ui>DNS / Nameservers</x-docs.ui> → back in TikTok click <x-docs.ui>Verify</x-docs.ui>.</p>
            <x-docs.copy :value="$urls['site'].'/storage/'" label="URL prefix the videos are served from" />
        </x-docs.step>
        <x-docs.step title="Copy the keys">
            <p>App page → <strong>Client key</strong> and <strong>Client secret</strong>.</p>
        </x-docs.step>
        <x-docs.step title="Submit the app for review">
            <p>Click <x-docs.ui>Submit for review</x-docs.ui>. Explain each product/scope and attach the demo video (connect TikTok in AffilStack → consent → publish a video). App review must pass before anyone outside your team can log in.</p>
        </x-docs.step>
        <x-docs.step title="Apply for the Content Posting audit">
            <p>After app approval, open the Content Posting API product and apply for the audit (TikTok links the form from its <x-docs.ext href="https://developers.tiktok.com/doc/content-sharing-guidelines">content sharing guidelines</x-docs.ext>). TikTok checks the posting screen against its UX rules: show the creator's TikTok name, a privacy dropdown with no default, Allow comment/duet/stitch options, the music-usage consent text, commercial-content disclosure, and a preview.</p>
            <x-docs.callout type="warning" title="Check the posting screen with your developer first">
                AffilStack's current TikTok publish step posts publicly with fixed settings. TikTok's audit requires the user to choose privacy and interaction settings on the posting screen — have your developer add the required elements before applying, or the audit will likely be refused.
            </x-docs.callout>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="paste" title="Where to paste in AffilStack" icon="heroicon-o-clipboard">
    <p><x-docs.admin-link route="filament.admin.pages.tik-tok-publishing-settings">Content → TikTok Publishing</x-docs.admin-link>: <strong>Client key</strong>, <strong>Client secret</strong>, turn on <x-docs.ui>Allow users to connect TikTok</x-docs.ui>. Set <strong>Content Posting API audit status</strong> (Not submitted / Pending / Approved) and keep case notes in <strong>Notes</strong> → <x-docs.ui>Save changes</x-docs.ui>. Only "Approved" makes AffilStack post for real.</p>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>Connected Accounts → <strong>Connect TikTok</strong> → authorize → the card shows your TikTok name and "Verified".</li>
        <li>After the audit is approved and marked Approved: publish a test UGC video — it appears on the TikTok profile within a few minutes (TikTok processes it in the background).</li>
        <li>The checklist card shows "Awaiting approval" while the audit status is Pending and <strong>Working</strong> once Approved.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="redirect_uri error on the TikTok login page">
        <p>The Login Kit redirect URI must be exactly <code>{{ $urls['tiktok_callback'] }}</code>.</p>
    </x-docs.trouble>
    <x-docs.trouble title="scope_not_authorized / unaudited_client_can_only_post_to_private_accounts">
        <p>The scope isn't approved yet, or the audit hasn't passed. Keep the audit status at Pending in AffilStack (manual posting) until TikTok approves.</p>
    </x-docs.trouble>
    <x-docs.trouble title="url_ownership_unverified">
        <p>TikTok hasn't verified the domain the video comes from — finish step 5 for <code>{{ $urls['host'] }}</code>.</p>
    </x-docs.trouble>
    <x-docs.trouble title="spam_risk_too_many_posts / daily cap reached">
        <p>TikTok limits posts per creator per day (roughly 15). Try again tomorrow.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Connection works only for my own account">
        <p>The app is still in sandbox/unreviewed. Only after app review can other TikTok users authorize it.</p>
    </x-docs.trouble>
</x-docs.section>
