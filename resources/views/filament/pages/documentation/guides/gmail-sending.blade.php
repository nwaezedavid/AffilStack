<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>With this on, customers see <strong>Connect Gmail</strong> on their <a href="{{ $urls['email_sending'] }}" target="_blank" rel="noopener">Email Sending</a> page and their CRM campaigns go out from their own Gmail address (up to the daily cap you set). AffilStack asks Google only for <code>gmail.send</code> — permission to send, not to read.</p>
    <ul>
        <li><strong>Google Sign-in fully set up</strong> and the app <strong>published</strong> ("In production").</li>
        <li><strong>{{ $urls['root_domain'] }} verified in Google Search Console</strong> by the same Google account (the <x-docs.guide-link key="site-analytics">Site Analytics</x-docs.guide-link> guide does this) — Google requires it for verification.</li>
        <li><strong>A Privacy Policy that explains Gmail data use</strong> (text to copy below).</li>
        <li><strong>A short demo video</strong> (screen recording, uploaded to YouTube as Unlisted).</li>
        <li><strong>Time:</strong> 30 minutes of work, then Google's review — often a few days, sometimes several weeks.</li>
    </ul>
    @include('filament.pages.documentation.guides._google-app')
    <x-docs.callout type="cost" title="Why verification is needed">
        <code>gmail.send</code> is a Google <strong>sensitive</strong> scope. Until Google approves it, every customer sees a "Google hasn't verified this app" warning, and only <strong>100 people in total, ever</strong>, can connect (a lifetime cap that can't be reset). Verification itself is free. Meanwhile customers can use <x-docs.guide-link key="customer-smtp">SMTP with an App Password</x-docs.guide-link>.
    </x-docs.callout>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Enable the Gmail API">
            <p>Google Cloud Console (AffilStack project) → <x-docs.ui>APIs &amp; Services</x-docs.ui> → <x-docs.ui>Library</x-docs.ui> → search <strong>Gmail API</strong> → <x-docs.ui>Enable</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step title="Confirm the redirect URI">
            <p>Google Auth Platform → <x-docs.ui>Clients</x-docs.ui> → your web client must list:</p>
            <x-docs.copy :value="$urls['gmail_callback']" label="Gmail redirect URI" />
        </x-docs.step>
        <x-docs.step title="Add the scope">
            <p>Google Auth Platform → <x-docs.ui>Data Access</x-docs.ui> → <x-docs.ui>Add or remove scopes</x-docs.ui> → find <code>https://www.googleapis.com/auth/gmail.send</code> (or paste it under "Manually add scopes") → <x-docs.ui>Update</x-docs.ui> → <x-docs.ui>Save</x-docs.ui>. Don't add <code>gmail.readonly</code> here yet — that one needs a paid security assessment (see <x-docs.guide-link key="gmail-inbox-reading">Gmail inbox reading</x-docs.guide-link>).</p>
        </x-docs.step>
        <x-docs.step title="Add Google's required wording to your Privacy Policy">
            <p>Edit the privacy page (Content → Site Pages → <strong>privacy</strong>) and add a section like:</p>
            <x-docs.copy value="Google user data: If you connect your Gmail account, AffilStack uses Google's gmail.send permission only to send the emails you create and schedule in your AffilStack CRM, from your own address. We do not read, store, sell or share the content of your mailbox, and we do not use Google user data for advertising or to train AI models. You can disconnect at any time from Email Sending or at myaccount.google.com/permissions. AffilStack's use and transfer of information received from Google APIs to any other app will adhere to the Google API Services User Data Policy, including the Limited Use requirements." label="Privacy Policy section" />
            <p>It must be reachable at <a href="{{ $urls['privacy'] }}" target="_blank" rel="noopener">{{ $urls['privacy'] }}</a>, on the same domain as the homepage.</p>
        </x-docs.step>
        <x-docs.step title="Record the demo video">
            <p>Record your screen (e.g. with Loom or OBS) showing, in English: the AffilStack homepage → log in → Email Sending → <strong>Connect Gmail</strong> → Google's consent screen (make sure the browser address bar with <code>client_id=…</code> is visible) → back in AffilStack, send a test campaign email and show it arriving. Upload to YouTube as <strong>Unlisted</strong>.</p>
        </x-docs.step>
        <x-docs.step title="Submit for verification">
            <p>Google Auth Platform → <x-docs.ui>Verification Center</x-docs.ui> → <x-docs.ui>Prepare for verification</x-docs.ui> / <x-docs.ui>Submit for verification</x-docs.ui>. Paste the demo video link and this justification:</p>
            <x-docs.copy value="AffilStack is a marketing platform for affiliate marketers. A signed-in user can connect their own Gmail account so the CRM email campaigns they write and approve inside AffilStack are sent from their own address instead of a shared server. We request only gmail.send; we never read or store mailbox content. The narrower scope is required because users must send from their own Gmail identity for deliverability and trust." label="Scope justification" />
            <p>Answer Google's follow-up emails quickly — reviews pause while they wait for you.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="paste" title="Where to switch it on in AffilStack" icon="heroicon-o-clipboard">
    <p><x-docs.admin-link route="filament.admin.pages.google-o-auth-settings">Site → Google Login</x-docs.admin-link> → <strong>Gmail sending (CRM nurture emails)</strong> → turn on <x-docs.ui>Allow users to connect Gmail for sending</x-docs.ui> → <x-docs.ui>Save changes</x-docs.ui>. No extra keys — it reuses the Client ID and secret.</p>
    <x-docs.callout type="tip" title="Before Google approves">
        You can turn it on for yourself and up to 100 people while waiting — they'll click through the "unverified app" warning. If you'd rather not show the warning, leave it off and let customers use SMTP until approval arrives.
    </x-docs.callout>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>As a customer: <a href="{{ $urls['email_sending'] }}" target="_blank" rel="noopener">Email Sending</a> → <strong>Connect Gmail</strong> → approve → the page shows "Sends from your real Gmail address" with the connected address. Click <x-docs.ui>Test connection</x-docs.ui>.</li>
        <li>After Google approves, the consent screen shows no warning, and Verification Center shows the scope as approved.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="redirect_uri_mismatch">
        <p>The Gmail redirect URI above isn't on the OAuth client — add it exactly.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Error 403: access_denied">
        <p>The app is still in Testing (publish it), or the user clicked Cancel / didn't tick "Send email on your behalf" on the consent screen.</p>
    </x-docs.trouble>
    <x-docs.trouble title="&quot;This app has reached its unverified user limit&quot;">
        <p>The 100-user lifetime cap for unverified sensitive scopes is used up. Only Google's verification lifts it; customers can use SMTP meanwhile.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Google rejected the verification">
        <p>Common reasons: the privacy policy doesn't mention Gmail/Limited Use, the demo video doesn't show the consent screen with the client ID, the homepage doesn't explain what AffilStack does, or the domain isn't verified in Search Console by a project owner. Fix and reply to Google's email.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Sending fails with 403 &quot;Gmail API has not been used in project&quot;">
        <p>Enable the Gmail API (step 1) in the same project as the Client ID.</p>
    </x-docs.trouble>
</x-docs.section>
