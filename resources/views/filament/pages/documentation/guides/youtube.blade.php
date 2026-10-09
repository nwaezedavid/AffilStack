<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>Customers connect their channel on <a href="{{ $urls['connected_accounts'] }}" target="_blank" rel="noopener">Connected Accounts</a>; AffilStack then uploads their rendered UGC videos with the YouTube Data API v3. It asks Google for <code>youtube.upload</code> (to upload) and <code>youtube.readonly</code> (to show and verify which channel is connected).</p>
    <ul>
        <li><strong>Google Sign-in set up and published</strong>, and the domain verified in Search Console.</li>
        <li><strong>Privacy Policy</strong> that mentions YouTube data, plus links to the <x-docs.ext href="https://www.youtube.com/t/terms">YouTube Terms of Service</x-docs.ext> and <x-docs.ext href="https://policies.google.com/privacy">Google Privacy Policy</x-docs.ext> (required by the YouTube API Services terms).</li>
        <li><strong>A demo video</strong> of the connect-and-publish flow (for Google's review).</li>
        <li><strong>Time:</strong> 30 minutes, then Google's OAuth verification (days to weeks).</li>
    </ul>
    @include('filament.pages.documentation.guides._google-app')
    <x-docs.callout type="cost" title="Limits to know (October 2026)">
        <ul>
            <li><strong>OAuth verification</strong>: both scopes are "sensitive" — until Google verifies them, customers see an "unverified app" warning and only <strong>100 people ever</strong> can connect.</li>
            <li><strong>Quota</strong>: every project gets <strong>10,000 units a day</strong> plus a separate bucket of <strong>100 uploads a day</strong> — shared by <em>all</em> your customers. More needs YouTube's compliance audit (free, via the Audit and Quota Extension form).</li>
        </ul>
    </x-docs.callout>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Enable the YouTube Data API v3">
            <p>Google Cloud Console (AffilStack project) → <x-docs.ui>APIs &amp; Services</x-docs.ui> → <x-docs.ui>Library</x-docs.ui> → <strong>YouTube Data API v3</strong> → <x-docs.ui>Enable</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step title="Confirm the redirect URI">
            <p>Google Auth Platform → <x-docs.ui>Clients</x-docs.ui> → your web client must list:</p>
            <x-docs.copy :value="$urls['youtube_callback']" label="YouTube redirect URI" />
        </x-docs.step>
        <x-docs.step title="Add the scopes">
            <p>Google Auth Platform → <x-docs.ui>Data Access</x-docs.ui> → <x-docs.ui>Add or remove scopes</x-docs.ui> → add <code>https://www.googleapis.com/auth/youtube.upload</code> and <code>https://www.googleapis.com/auth/youtube.readonly</code> → <x-docs.ui>Update</x-docs.ui> → <x-docs.ui>Save</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step title="Submit for OAuth verification">
            <p><x-docs.ui>Verification Center</x-docs.ui> → submit (you can combine this with the Gmail submission). Demo video: log in → Connected Accounts → <strong>Connect YouTube</strong> → Google consent screen with <code>client_id</code> visible in the address bar → publish a UGC video → show it on the channel. Justification:</p>
            <x-docs.copy value="AffilStack generates short user-generated-content videos for affiliate marketers. A signed-in user can connect their own YouTube channel and choose to publish a finished video to it. youtube.upload is used only to upload the video the user explicitly publishes; youtube.readonly is used only to show which channel is connected. We never modify or delete existing videos." label="Scope justification" />
        </x-docs.step>
        <x-docs.step title="(When you outgrow 100 uploads a day) request an audit">
            <p>Fill in the <x-docs.ext href="https://support.google.com/youtube/contact/yt_api_form">YouTube API Services – Audit and Quota Extension Form</x-docs.ext>. YouTube reviews AffilStack against its API terms (privacy policy, how videos and data are handled) and then raises the quota.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="paste" title="Where to switch it on in AffilStack" icon="heroicon-o-clipboard">
    <x-docs.steps>
        <x-docs.step>
            <p><x-docs.admin-link route="filament.admin.pages.google-o-auth-settings">Site → Google Login</x-docs.admin-link> → <strong>YouTube publishing (UGC videos)</strong> → turn on <x-docs.ui>Allow users to connect YouTube</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step>
            <p>Set <strong>Audit + Quota Extension status</strong>: "Submitted — awaiting review" while Google reviews. AffilStack only uploads for real once it's <strong>Approved</strong> — until then customers get "download and post manually". Switch it to Approved when Google has verified the scopes (and, if you need more than 100 uploads a day, granted the quota). Use <strong>Notes</strong> for case numbers → <x-docs.ui>Save changes</x-docs.ui>.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>As a customer: Connected Accounts → <strong>Connect YouTube</strong> → approve → the YouTube card shows the channel name and "Verified".</li>
        <li>Publish a test UGC video — it appears on the channel (YouTube Studio → Content). Delete it afterwards.</li>
        <li>Cloud Console → <x-docs.ui>APIs &amp; Services</x-docs.ui> → YouTube Data API v3 → <x-docs.ui>Quotas</x-docs.ui> shows today's usage.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="redirect_uri_mismatch">
        <p>Add the YouTube redirect URI above to the OAuth client exactly.</p>
    </x-docs.trouble>
    <x-docs.trouble title="403 quotaExceeded / &quot;The request cannot be completed because you have exceeded your quota&quot;">
        <p>The project used today's 100 uploads or 10,000 units (resets at midnight US Pacific time). Request an audit/extension for more.</p>
    </x-docs.trouble>
    <x-docs.trouble title="403 youtubeSignupRequired">
        <p>The Google account has no YouTube channel. The customer must create one at youtube.com first.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Videos upload as Private">
        <p>Older YouTube rules forced uploads from unaudited projects to Private; Google's current documentation says this no longer applies. If you still see it, it's the channel's own default upload visibility (YouTube Studio → Settings → Upload defaults) — or submit the audit form.</p>
    </x-docs.trouble>
    <x-docs.trouble title="access_denied / unverified app warning">
        <p>Scopes not yet verified, or the 100-user cap is reached. Only Google's verification removes this.</p>
    </x-docs.trouble>
</x-docs.section>
