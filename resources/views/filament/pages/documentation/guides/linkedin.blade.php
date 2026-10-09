<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>Customers click <strong>Connect LinkedIn</strong> on <a href="{{ $urls['connected_accounts'] }}" target="_blank" rel="noopener">Connected Accounts</a> so AffilStack knows which LinkedIn profile their exported posts and messages belong to. AffilStack only asks for <code>openid profile email</code> — it <strong>never posts</strong> to LinkedIn; customers copy or export the content and post it themselves.</p>
    <ul>
        <li><strong>A LinkedIn account</strong> and a <strong>LinkedIn Company Page</strong> for AffilStack — LinkedIn won't create a developer app without a Page (create one free: LinkedIn → For Business → Create a Company Page).</li>
        <li><strong>A square logo</strong> (at least 100×100 px).</li>
        <li><strong>About 20 minutes.</strong> Free, no LinkedIn review needed for this product.</li>
    </ul>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Create the app">
            <p>Go to <x-docs.ext href="https://www.linkedin.com/developers/apps">linkedin.com/developers/apps</x-docs.ext> → <x-docs.ui>Create app</x-docs.ui>. App name <code>AffilStack</code>; LinkedIn Page: pick your AffilStack page; Privacy policy URL:</p>
            <x-docs.copy :value="$urls['privacy']" label="Privacy policy URL" />
            <p>Upload the logo, tick the legal agreement → <x-docs.ui>Create app</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step title="Verify the app with your Page">
            <p><x-docs.ui>Settings</x-docs.ui> tab → <x-docs.ui>Verify</x-docs.ui> → <x-docs.ui>Generate URL</x-docs.ui>. Open that link while logged in as a Page admin and approve. The app now shows "Verified".</p>
        </x-docs.step>
        <x-docs.step title="Add Sign In with LinkedIn using OpenID Connect">
            <p><x-docs.ui>Products</x-docs.ui> tab → <strong>Sign In with LinkedIn using OpenID Connect</strong> → <x-docs.ui>Request access</x-docs.ui> → accept the terms. It's approved instantly and grants <code>openid</code>, <code>profile</code> and <code>email</code>.</p>
            <x-docs.callout type="info" title="You don't need these products">
                <strong>Share on LinkedIn</strong> (<code>w_member_social</code>, posting on a member's behalf) and the <strong>Community Management API</strong> (posting to Company Pages — needs a LinkedIn application and vetting) aren't used by AffilStack. Don't request them; extra products only slow LinkedIn's checks.
            </x-docs.callout>
        </x-docs.step>
        <x-docs.step title="Add the redirect URL">
            <p><x-docs.ui>Auth</x-docs.ui> tab → <strong>OAuth 2.0 settings</strong> → <strong>Authorized redirect URLs for your app</strong> → pencil icon → <x-docs.ui>Add redirect URL</x-docs.ui>:</p>
            <x-docs.copy :value="$urls['linkedin_callback']" label="Authorized redirect URL" />
            <p><x-docs.ui>Update</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step title="Copy the keys">
            <p>Same <x-docs.ui>Auth</x-docs.ui> tab → <strong>Application credentials</strong>: copy the <strong>Client ID</strong> and the <strong>Primary Client Secret</strong> (click the eye icon).</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="paste" title="Where to paste in AffilStack" icon="heroicon-o-clipboard">
    <p><x-docs.admin-link route="filament.admin.pages.linked-in-o-auth-settings">Site → LinkedIn Connect</x-docs.admin-link>: <strong>Client ID</strong>, <strong>Client secret</strong>, switch <x-docs.ui>Enabled</x-docs.ui> on → <x-docs.ui>Save changes</x-docs.ui>.</p>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>LinkedIn can't be checked with keys alone, so test it like a customer: Connected Accounts → <strong>Connect LinkedIn</strong> → <strong>Allow</strong>. The LinkedIn card shows your name and "Verified".</li>
        <li>The checklist card shows "Set up — not verified" (expected for LinkedIn) until a real connection succeeds.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="&quot;The redirect_uri does not match the registered value&quot;">
        <p>The redirect URL in the Auth tab differs from <code>{{ $urls['linkedin_callback'] }}</code> (https, www, trailing slash). Fix it and click Update.</p>
    </x-docs.trouble>
    <x-docs.trouble title="unauthorized_scope_error: Scope &quot;openid&quot; is not authorized">
        <p>The <strong>Sign In with LinkedIn using OpenID Connect</strong> product isn't added (Products tab).</p>
    </x-docs.trouble>
    <x-docs.trouble title="invalid_client / &quot;Client authentication failed&quot;">
        <p>The secret was copied wrong or regenerated. Copy the current Primary Client Secret again.</p>
    </x-docs.trouble>
    <x-docs.trouble title="The Connect LinkedIn button is missing">
        <p>The Enabled toggle is off or the Client ID is empty.</p>
    </x-docs.trouble>
</x-docs.section>
