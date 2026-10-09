<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>Customers connect Instagram on <a href="{{ $urls['connected_accounts'] }}" target="_blank" rel="noopener">Connected Accounts</a> through <strong>Facebook Login for Business</strong>; AffilStack then publishes their UGC videos as <strong>Reels</strong> with the Instagram Graph API. Permissions requested: <code>instagram_basic</code>, <code>instagram_content_publish</code>, <code>pages_read_engagement</code>, <code>pages_show_list</code>.</p>
    <ul>
        <li><strong>A Meta developer account</strong> at <x-docs.ext href="https://developers.facebook.com/">developers.facebook.com</x-docs.ext> and a <strong>Meta Business portfolio</strong> (business.facebook.com).</li>
        <li><strong>Business Verification</strong> of that portfolio (company documents) — required for Advanced Access.</li>
        <li><strong>Privacy Policy, Terms and data-deletion instructions</strong> pages, an app icon (1024×1024) and screen recordings for App Review.</li>
        <li><strong>Time:</strong> about an hour, then Meta's App Review (days to a few weeks).</li>
    </ul>
    <x-docs.callout type="info" title="What each customer needs">
        An Instagram <strong>professional</strong> account (Business or Creator — free to switch in the Instagram app) <strong>connected to a Facebook Page</strong> they manage. Personal Instagram accounts can't publish through the API. Each account can publish up to about 100 posts per 24 hours via the API.
    </x-docs.callout>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Create the Meta app">
            <p>developers.facebook.com → <x-docs.ui>My Apps</x-docs.ui> → <x-docs.ui>Create app</x-docs.ui>. Name <code>AffilStack</code>, your contact email. When asked for a use case choose <x-docs.ui>Other</x-docs.ui>, then app type <x-docs.ui>Business</x-docs.ui>, and connect your Business portfolio → <x-docs.ui>Create app</x-docs.ui>. (Meta reshuffles this wizard often — the goal is a Business-type app that can add "Facebook Login for Business" and "Instagram".)</p>
        </x-docs.step>
        <x-docs.step title="Basic settings">
            <p><x-docs.ui>App settings</x-docs.ui> → <x-docs.ui>Basic</x-docs.ui>:</p>
            <x-docs.copy :value="$urls['root_domain']" label="App domains" />
            <x-docs.copy :value="$urls['privacy']" label="Privacy Policy URL" />
            <x-docs.copy :value="$urls['terms']" label="Terms of Service URL" />
            <x-docs.copy :value="$urls['privacy']" label="User data deletion → Data deletion instructions URL" />
            <p>Add a section to the privacy page explaining how a customer disconnects Instagram (Connected Accounts → Disconnect) and asks you to delete their data. Pick a category, upload the icon → <x-docs.ui>Save changes</x-docs.ui>. Copy the <strong>App ID</strong> and <strong>App secret</strong> (click Show).</p>
        </x-docs.step>
        <x-docs.step title="Add Facebook Login for Business + the redirect URI">
            <p><x-docs.ui>Add product</x-docs.ui> → <x-docs.ui>Facebook Login for Business</x-docs.ui> → <x-docs.ui>Settings</x-docs.ui>. Keep <strong>Client OAuth login</strong>, <strong>Web OAuth login</strong> and <strong>Enforce HTTPS</strong> on. In <strong>Valid OAuth Redirect URIs</strong> paste:</p>
            <x-docs.copy :value="$urls['instagram_callback']" label="Valid OAuth Redirect URI" />
            <p><x-docs.ui>Save changes</x-docs.ui>. Also add the <x-docs.ui>Instagram</x-docs.ui> product (Instagram API with Facebook Login) if the dashboard offers it.</p>
        </x-docs.step>
        <x-docs.step title="Verify the business">
            <p>business.facebook.com → <x-docs.ui>Settings</x-docs.ui> → <x-docs.ui>Business info</x-docs.ui> / <x-docs.ui>Security Centre</x-docs.ui> → <x-docs.ui>Start verification</x-docs.ui>. Upload a CAC certificate or utility bill matching the business name and address.</p>
        </x-docs.step>
        <x-docs.step title="Request Advanced Access (App Review)">
            <p>App dashboard → <x-docs.ui>App Review</x-docs.ui> → <x-docs.ui>Permissions and Features</x-docs.ui>. Request <strong>Advanced Access</strong> for <code>instagram_basic</code>, <code>instagram_content_publish</code>, <code>pages_read_engagement</code> and <code>pages_show_list</code>. For each, describe the use ("lets a customer publish the Reel they created in AffilStack to their own Instagram account") and upload a screencast: log in to AffilStack → Connected Accounts → Connect Instagram → Facebook permission dialog → publish a video → the Reel on Instagram. Submit.</p>
            <p class="afs-g-small">Before approval only people with a role on the app (App roles → add yourself and testers) can connect.</p>
        </x-docs.step>
        <x-docs.step title="Go Live">
            <p>When review passes, switch <strong>App Mode</strong> at the top of the dashboard from <x-docs.ui>Development</x-docs.ui> to <x-docs.ui>Live</x-docs.ui>.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="paste" title="Where to paste in AffilStack" icon="heroicon-o-clipboard">
    <p><x-docs.admin-link route="filament.admin.pages.instagram-publishing-settings">Content → Instagram Publishing</x-docs.admin-link>: <strong>App ID</strong>, <strong>App secret</strong>, turn on <x-docs.ui>Allow users to connect Instagram</x-docs.ui>, set <strong>App Review status</strong> (Pending while Meta reviews, Approved after) → <x-docs.ui>Save changes</x-docs.ui>. Only "Approved" makes AffilStack publish for real; before that customers get "download and post manually".</p>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>Connected Accounts → <strong>Connect Instagram</strong> → pick the Facebook Page linked to the Instagram account → the card shows the Instagram username and "Verified".</li>
        <li>After approval: publish a test UGC video — it appears as a Reel on the profile.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="&quot;URL blocked: This redirect failed because the redirect URI is not whitelisted&quot;">
        <p>Add <code>{{ $urls['instagram_callback'] }}</code> to Valid OAuth Redirect URIs exactly, and make sure the app domain is listed in Basic settings.</p>
    </x-docs.trouble>
    <x-docs.trouble title="No Instagram account found after connecting">
        <p>The Instagram account is personal, or not linked to a Facebook Page the customer selected in the permission dialog. Switch to a professional account and link it to the Page (Instagram app → Settings → Account type and tools / Accounts Centre).</p>
    </x-docs.trouble>
    <x-docs.trouble title="(#10) Application does not have permission for this action / (#200) permissions error">
        <p>Advanced Access isn't approved yet, or the customer unticked a permission. Re-connect and allow all; check App Review status.</p>
    </x-docs.trouble>
    <x-docs.trouble title="&quot;App not active&quot; / only testers can log in">
        <p>App Mode is still Development. Switch to Live after approval.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Reel fails to publish">
        <p>Instagram fetches the video from <code>{{ $urls['site'] }}/storage/…</code> — the site must be publicly reachable over https. The account may also have hit the 24-hour publishing limit.</p>
    </x-docs.trouble>
</x-docs.section>
