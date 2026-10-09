<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <ul>
        <li><strong>A Google account you'll keep for the business</strong> (e.g. the one you use for Google Analytics). Everything is owned by this account — don't use a staff member's personal account.</li>
        <li><strong>The live site on https</strong> — <code>{{ $urls['site'] }}</code>. Google refuses plain-http redirect addresses.</li>
        <li><strong>Published Privacy Policy and Terms pages</strong> — Google asks for both: <a href="{{ $urls['privacy'] }}" target="_blank" rel="noopener">{{ $urls['privacy'] }}</a> and <a href="{{ $urls['terms'] }}" target="_blank" rel="noopener">{{ $urls['terms'] }}</a>.</li>
        <li><strong>About 20 minutes.</strong> Free.</li>
    </ul>
    <x-docs.callout type="tip" title="One Google app powers four features">
        The Client ID you create here is reused by <x-docs.guide-link key="gmail-sending">Gmail sending</x-docs.guide-link>, <x-docs.guide-link key="youtube">YouTube publishing</x-docs.guide-link> and <x-docs.guide-link key="site-analytics">Site Analytics</x-docs.guide-link>. This guide registers all four redirect addresses at once so you never have to come back to this screen.
    </x-docs.callout>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <h3>A. Create the Google Cloud project</h3>
    <x-docs.steps>
        <x-docs.step title="Open Google Cloud Console">
            <p>Go to <x-docs.ext href="https://console.cloud.google.com/">console.cloud.google.com</x-docs.ext> and sign in with the business Google account. Accept the terms if it's your first visit.</p>
        </x-docs.step>
        <x-docs.step title="Create a project">
            <p>Click the <x-docs.ui>project picker</x-docs.ui> in the top bar → <x-docs.ui>New project</x-docs.ui>. Project name: <code>AffilStack</code>. Leave Organization as is → <x-docs.ui>Create</x-docs.ui>. When it's ready, make sure the top bar shows <strong>AffilStack</strong> as the selected project.</p>
        </x-docs.step>
    </x-docs.steps>

    <h3>B. Set up the consent screen (Google Auth Platform)</h3>
    <x-docs.steps>
        <x-docs.step title="Open Google Auth Platform">
            <p>Menu <x-docs.ui>☰</x-docs.ui> → <x-docs.ui>APIs &amp; Services</x-docs.ui> → <x-docs.ui>OAuth consent screen</x-docs.ui> (or go straight to <x-docs.ext href="https://console.cloud.google.com/auth/overview">console.cloud.google.com/auth/overview</x-docs.ext>) → <x-docs.ui>Get started</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step title="App information">
            <p>App name: <code>AffilStack</code> (this is what people see on Google's "Sign in to …" screen). User support email: your business address → <x-docs.ui>Next</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step title="Audience">
            <p>Choose <x-docs.ui>External</x-docs.ui> (anyone with a Google account) → <x-docs.ui>Next</x-docs.ui>. "Internal" only works for Google Workspace staff and would block every customer.</p>
        </x-docs.step>
        <x-docs.step title="Contact information">
            <p>Your email address (Google sends policy notices here) → <x-docs.ui>Next</x-docs.ui> → tick the user-data policy box → <x-docs.ui>Continue</x-docs.ui> → <x-docs.ui>Create</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step title="Branding — add your links">
            <p>Left menu <x-docs.ui>Branding</x-docs.ui>. Fill in:</p>
            <x-docs.copy :value="$urls['site']" label="Application home page" />
            <x-docs.copy :value="$urls['privacy']" label="Application privacy policy link" />
            <x-docs.copy :value="$urls['terms']" label="Application terms of service link" />
            <x-docs.copy :value="$urls['root_domain']" label="Authorized domains → Add domain" />
            <p>Developer contact information: your email → <x-docs.ui>Save</x-docs.ui>.</p>
            <x-docs.callout type="warning" title="Skip the logo for now">
                Uploading an app logo makes Google run a <strong>brand verification</strong> (usually 2–3 business days, and it needs the domain verified in Search Console). Without a logo, "Continue with Google" works immediately. Add the logo later, together with the Gmail/YouTube verification.
            </x-docs.callout>
        </x-docs.step>
        <x-docs.step title="Data Access — the sign-in scopes">
            <p>Left menu <x-docs.ui>Data Access</x-docs.ui> → <x-docs.ui>Add or remove scopes</x-docs.ui>. Tick <code>openid</code>, <code>.../auth/userinfo.email</code> and <code>.../auth/userinfo.profile</code> → <x-docs.ui>Update</x-docs.ui> → <x-docs.ui>Save</x-docs.ui>. These three are "non-sensitive", so sign-in needs no Google review.</p>
        </x-docs.step>
        <x-docs.step title="Audience — publish the app">
            <p>Left menu <x-docs.ui>Audience</x-docs.ui> → under Publishing status click <x-docs.ui>Publish app</x-docs.ui> → <x-docs.ui>Confirm</x-docs.ui>. Status changes from "Testing" to <strong>"In production"</strong>.</p>
            <x-docs.callout type="danger" title="Don't leave it in Testing">
                In Testing only the (max 100) test users you list can sign in, and Google expires their access <strong>7 days</strong> after they consent — which silently breaks Gmail, YouTube and Site Analytics connections every week.
            </x-docs.callout>
        </x-docs.step>
    </x-docs.steps>

    <h3>C. Create the OAuth client (the keys)</h3>
    <x-docs.steps>
        <x-docs.step title="Create a Web application client">
            <p>Left menu <x-docs.ui>Clients</x-docs.ui> → <x-docs.ui>Create client</x-docs.ui>. Application type: <x-docs.ui>Web application</x-docs.ui>. Name: <code>AffilStack web</code>.</p>
        </x-docs.step>
        <x-docs.step title="Authorized JavaScript origins (optional)">
            <p>AffilStack signs in on the server, so this isn't required — adding it does no harm:</p>
            <x-docs.copy :value="$urls['site']" label="Authorized JavaScript origin" />
        </x-docs.step>
        <x-docs.step title="Authorized redirect URIs — add all four">
            <p>Click <x-docs.ui>Add URI</x-docs.ui> four times and paste each line exactly (https, no extra slash at the end):</p>
            <x-docs.copy :value="$urls['google_callback']" label="1 · Sign-in (Continue with Google)" />
            <x-docs.copy :value="$urls['gmail_callback']" label="2 · Gmail sending (customers' Email Sending page)" />
            <x-docs.copy :value="$urls['youtube_callback']" label="3 · YouTube publishing (Connected Accounts)" />
            <x-docs.copy :value="$urls['site_analytics_callback']" label="4 · Site Analytics (GA4 / Search Console / Tag Manager)" />
            <p>Click <x-docs.ui>Create</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step title="Copy the Client ID and Client secret now">
            <p>The pop-up shows the <strong>Client ID</strong> (ends in <code>.apps.googleusercontent.com</code>) and the <strong>Client secret</strong> (starts with <code>GOCSPX-</code>). Copy both, or click <x-docs.ui>Download JSON</x-docs.ui>.</p>
            <x-docs.callout type="warning" title="The secret is shown only once">
                Since June 2025 Google shows a client secret only on this creation screen. If you lose it, open the client → <x-docs.ui>Add secret</x-docs.ui>, paste the new one into AffilStack, then disable the old one.
            </x-docs.callout>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="paste" title="Where to paste in AffilStack" icon="heroicon-o-clipboard">
    <x-docs.steps>
        <x-docs.step>
            <p>Open <x-docs.admin-link route="filament.admin.pages.google-o-auth-settings">Site → Google Login</x-docs.admin-link>.</p>
        </x-docs.step>
        <x-docs.step>
            <p>Paste <strong>Client ID</strong> and <strong>Client secret</strong>, switch <x-docs.ui>Enabled</x-docs.ui> on, click <x-docs.ui>Save changes</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step>
            <p>Click <x-docs.ui>Check credentials</x-docs.ui> at the top — you should see "Looks good".</p>
        </x-docs.step>
    </x-docs.steps>
    <p>Leave the Gmail and YouTube toggles off until you've done their guides.</p>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>Open a private/incognito window → <a href="{{ $urls['login'] }}" target="_blank" rel="noopener">{{ $urls['login'] }}</a> → <strong>Continue with Google</strong> → pick an account. You should land in the dashboard (a new account is created the first time).</li>
        <li>The <strong>Google Sign-in</strong> card on the <x-docs.admin-link route="filament.admin.documentation.pages.integration-guides">setup checklist</x-docs.admin-link> and on Connections Health turns green after "Run all live checks".</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="Error 400: redirect_uri_mismatch">
        <p>The address Google received isn't in the client's <strong>Authorized redirect URIs</strong> list character-for-character. Check: <code>https</code> (not http), <code>www.</code> vs no www, no trailing <code>/</code>, and that you edited the right client in the right project. AffilStack sends exactly <code>{{ $urls['google_callback'] }}</code>. If that address shows the wrong domain, <code>APP_URL</code> in the server <code>.env</code> is wrong. Changes in Google can take up to 5 minutes to apply.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Error 401: invalid_client / &quot;The OAuth client was not found&quot;">
        <p>The Client ID or secret in AffilStack doesn't match Google. Re-copy both (no spaces at either end), and check the secret is still enabled under the client's <strong>Client secrets</strong>. Google also <strong>deletes OAuth clients unused for 6 months</strong> (restorable for 30 days under Clients → deleted clients) — if the site was offline that long, restore it or create a new client.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Error 403: access_denied / &quot;Access blocked: AffilStack has not completed the Google verification process&quot;">
        <p>The app is still in <strong>Testing</strong> and the person isn't a listed test user. Go to <strong>Audience</strong> → <strong>Publish app</strong>. If it appears only on Gmail/YouTube/Analytics connects, those scopes still need Google verification (see their guides).</p>
    </x-docs.trouble>
    <x-docs.trouble title="&quot;Google hasn't verified this app&quot; warning">
        <p>Shown when an app asks for a <em>sensitive</em> scope (Gmail, YouTube, Analytics) before Google approved it. Plain sign-in never shows it. You can continue via <strong>Advanced → Go to AffilStack (unsafe)</strong> for your own admin connections; customers need the verification described in the Gmail/YouTube guides.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Error 403: org_internal">
        <p>Audience is set to <strong>Internal</strong>. Change it to <strong>External</strong> under Audience.</p>
    </x-docs.trouble>
    <x-docs.trouble title="The &quot;Continue with Google&quot; button doesn't appear">
        <p>The <strong>Enabled</strong> toggle on Site → Google Login is off, or the Client ID is empty. Save again and refresh the sign-in page.</p>
    </x-docs.trouble>
</x-docs.section>
