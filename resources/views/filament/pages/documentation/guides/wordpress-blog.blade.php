<x-docs.callout type="danger" title="Read this first — this is what broke the live site last time" big>
    <p>On Hostinger, <strong>{{ $urls['root_domain'] }}'s <code>public_html</code> is not a normal folder</strong>: it's a shortcut (symlink) to the AffilStack app's <code>public</code> folder (<code>domains/{{ $urls['root_domain'] }}/public_html → affilstack/public</code>).</p>
    <p>Creating the blog with <strong>Domains → Subdomains</strong> on the {{ $urls['root_domain'] }} website (or the WordPress installer with a "blog" directory) puts WordPress <strong>inside <code>public_html</code> — i.e. inside the AffilStack app</strong>. WordPress then overwrites or confuses the app's files and the whole platform goes down.</p>
    <p><strong>Always create the blog as its own, separate website</strong> (step B below). Its files then live in their own folder, completely outside AffilStack.</p>
</x-docs.callout>

<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <ul>
        <li><strong>A free website slot on your Hostinger plan</strong> — the blog counts as one website (hPanel → Websites shows how many you use).</li>
        <li><strong>A fresh backup of AffilStack</strong>: hPanel → <x-docs.ui>Websites</x-docs.ui> → {{ $urls['root_domain'] }} → <x-docs.ui>Dashboard</x-docs.ui> → <x-docs.ui>Backups</x-docs.ui> → generate a new backup. Takes two minutes and makes any mistake reversible.</li>
        <li><strong>About 30 minutes.</strong> No extra cost.</li>
    </ul>
    <h3>Never do these</h3>
    <ul>
        <li>Don't use <strong>Domains → Subdomains</strong> inside the {{ $urls['root_domain'] }} website to create <code>blog</code>.</li>
        <li>Don't install WordPress on <strong>{{ $urls['root_domain'] }}</strong> with a directory like <code>/blog</code>.</li>
        <li>Don't upload, move or delete anything inside {{ $urls['root_domain'] }}'s <code>public_html</code>, and never replace that shortcut with a real folder.</li>
    </ul>
</x-docs.section>

<x-docs.section id="cleanup" title="A. If a previous attempt exists — clean it up safely" icon="heroicon-o-trash">
    <x-docs.steps>
        <x-docs.step title="Remove the old subdomain entry">
            <p>hPanel → <x-docs.ui>Websites</x-docs.ui> → {{ $urls['root_domain'] }} → <x-docs.ui>Dashboard</x-docs.ui> → <x-docs.ui>Domains</x-docs.ui> → <x-docs.ui>Subdomains</x-docs.ui>. If <code>blog.{{ $urls['root_domain'] }}</code> is listed there, delete it (this deletes only the subdomain mapping).</p>
        </x-docs.step>
        <x-docs.step title="Remove leftover WordPress files from inside the app — carefully">
            <p>File Manager → open the AffilStack app's <code>public</code> folder (the one public_html points to). If there is a <code>blog</code> folder (containing <code>wp-admin</code>, <code>wp-content</code>, <code>wp-config.php</code>), delete <strong>only that <code>blog</code> folder</strong>. Never delete <code>index.php</code>, <code>.htaccess</code>, <code>build</code>, <code>css</code>, <code>js</code>, <code>storage</code> or anything else in <code>public</code>.</p>
            <p>Not sure? Stop and ask your developer — or restore the backup you just made if the site misbehaves.</p>
        </x-docs.step>
        <x-docs.step title="Check AffilStack still loads">
            <p>Open <a href="{{ $urls['site'] }}" target="_blank" rel="noopener">{{ $urls['site'] }}</a> and log in to the dashboard before continuing.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="steps" title="B. Create the blog as a separate website" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Start Add website">
            <p>hPanel → <x-docs.ui>Websites</x-docs.ui> → <x-docs.ui>Add website</x-docs.ui> (top right). Choose <x-docs.ui>WordPress</x-docs.ui>. (Hostinger calls this "creating the subdomain as an independent website" — its recommended method.)</p>
        </x-docs.step>
        <x-docs.step title="Use the subdomain as the domain">
            <p>When asked for a domain, choose <x-docs.ui>Use an existing domain</x-docs.ui> and type:</p>
            <x-docs.copy :value="'blog.'.$urls['root_domain']" label="Domain" />
            <p>Do <strong>not</strong> pick {{ $urls['root_domain'] }} itself. Continue through the WordPress steps: set the WordPress admin email and a strong password, language, and skip the optional plugins/AI theme if you like.</p>
        </x-docs.step>
        <x-docs.step title="Confirm the files landed outside AffilStack">
            <p>File Manager → go to your home folder → <code>domains</code>. You should now see a <strong>separate</strong> <code>blog.{{ $urls['root_domain'] }}</code> folder with its own <code>public_html</code> containing <code>wp-admin</code>, <code>wp-content</code>… — and <strong>nothing new</strong> inside the AffilStack app folder.</p>
            <x-docs.callout type="warning" title="If WordPress files appear inside the AffilStack app instead">
                Stop, delete that WordPress installation from hPanel → WordPress, and go back to section A. Hostinger's screens change; the rule doesn't: the blog's folder must not be under {{ $urls['root_domain'] }}'s <code>public_html</code>.
            </x-docs.callout>
        </x-docs.step>
        <x-docs.step title="DNS">
            <p>If {{ $urls['root_domain'] }} uses Hostinger's nameservers this is automatic (allow a few minutes). If your DNS is elsewhere (e.g. Cloudflare), add an <strong>A</strong> record: Name <code>blog</code> → your hosting plan's IP (hPanel → the blog website → Dashboard → Details/IP address).</p>
        </x-docs.step>
        <x-docs.step title="SSL (https)">
            <p>hPanel → <x-docs.ui>Websites</x-docs.ui> → blog.{{ $urls['root_domain'] }} → <x-docs.ui>Security</x-docs.ui> → <x-docs.ui>SSL</x-docs.ui>. The free SSL is usually installed automatically; if not, click <x-docs.ui>Install SSL</x-docs.ui>. Wait until it says Active, then open <code>https://blog.{{ $urls['root_domain'] }}</code> — no browser warning.</p>
        </x-docs.step>
        <x-docs.step title="WordPress settings that AffilStack needs">
            <p>Log in at <code>https://blog.{{ $urls['root_domain'] }}/wp-admin</code>:</p>
            <ul>
                <li><x-docs.ui>Settings</x-docs.ui> → <x-docs.ui>General</x-docs.ui>: <strong>WordPress Address</strong> and <strong>Site Address</strong> both <code>https://blog.{{ $urls['root_domain'] }}</code> → Save.</li>
                <li><x-docs.ui>Settings</x-docs.ui> → <x-docs.ui>Permalinks</x-docs.ui> → <x-docs.ui>Post name</x-docs.ui> → <x-docs.ui>Save Changes</x-docs.ui>. (This turns on the <code>/wp-json/</code> addresses AffilStack reads.)</li>
                <li>Publish at least one post with a <strong>Featured image</strong> — the homepage shows it.</li>
            </ul>
        </x-docs.step>
        <x-docs.step title="Check the blog's feed in your browser">
            <p>Open this address:</p>
            <x-docs.copy :value="'https://blog.'.$urls['root_domain'].'/wp-json/wp/v2/posts'" label="WordPress posts feed" />
            <p>You should see raw text starting with <code>[{"id":</code> — that's JSON, and it means AffilStack can read the blog.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="paste" title="C. Connect it in AffilStack" icon="heroicon-o-clipboard">
    <x-docs.steps>
        <x-docs.step>
            <p>Open <x-docs.admin-link route="filament.admin.pages.homepage-editor">Website → Homepage</x-docs.admin-link> → tab <x-docs.ui>12. Blog articles</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step>
            <p><strong>Blog address</strong>: <code>https://blog.{{ $urls['root_domain'] }}</code> (the home page, not the wp-json address). Click <x-docs.ui>Test blog connection</x-docs.ui> — expect "Blog connected — N posts found".</p>
        </x-docs.step>
        <x-docs.step>
            <p>Make sure <strong>Show latest blog articles on the homepage</strong> is on, choose how many articles to show → <x-docs.ui>Save homepage</x-docs.ui>. "Blog" is added to the Resources menu and footer automatically.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>The AffilStack homepage shows your latest posts with their featured images (refreshed hourly — publishing a new post shows up within the hour).</li>
        <li><a href="{{ $urls['site'] }}" target="_blank" rel="noopener">{{ $urls['site'] }}</a> and the dashboard still work exactly as before.</li>
        <li>The checklist card turns <strong>Working</strong> once posts have been fetched.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="AffilStack went down / shows errors after creating the blog">
        <p>The blog was created inside the app. Restore the backup (hPanel → {{ $urls['root_domain'] }} → Backups) or follow section A, then redo section B.</p>
    </x-docs.trouble>
    <x-docs.trouble title="The wp-json address shows a 404 page">
        <p>Permalinks are still "Plain" — set them to Post name and save. If it persists, the blog's <code>.htaccess</code> is missing WordPress's rewrite rules: saving Permalinks again usually rewrites it.</p>
    </x-docs.trouble>
    <x-docs.trouble title="The wp-json address says rest_disabled / 401 / &quot;Sorry, you are not allowed&quot;">
        <p>A security plugin (e.g. "Disable REST API", Wordfence, iThemes/Solid Security, All-In-One WP Security) blocks the public REST API. Allow anonymous access to <code>/wp/v2/posts</code> or turn that option off.</p>
    </x-docs.trouble>
    <x-docs.trouble title="&quot;Test blog connection&quot; fails but the address works in my browser">
        <p>Check the Blog address uses <code>https://</code> and has no path after the domain; check the blog's SSL is Active; a caching/CDN firewall (e.g. Cloudflare "Under attack" mode) may block servers — allow it.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Hostinger says I've reached my website limit">
        <p>Remove an unused website or upgrade the plan. Don't fall back to Domains → Subdomains on {{ $urls['root_domain'] }} — that's the method that breaks AffilStack.</p>
    </x-docs.trouble>
</x-docs.section>
