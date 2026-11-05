<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>AffilStack sends its own emails (sign-up confirmation, password reset, receipts, lifecycle emails, admin alerts) through one mailbox you own, configured in the server <code>.env</code> file. This is separate from the mailboxes <em>customers</em> connect for their campaigns (see <x-docs.guide-link key="customer-smtp">Customer SMTP</x-docs.guide-link>).</p>
    <ul>
        <li><strong>A mailbox on your domain</strong>, e.g. <code>hello@{{ $urls['root_domain'] }}</code> or <code>no-reply@{{ $urls['root_domain'] }}</code>. Hostinger Email is included with most Hostinger plans: hPanel → <x-docs.ui>Emails</x-docs.ui> → <x-docs.ui>Email accounts</x-docs.ui> → <x-docs.ui>Create email account</x-docs.ui>.</li>
        <li><strong>The mailbox password</strong> (not your hPanel password).</li>
        <li><strong>Access to DNS</strong> for {{ $urls['root_domain'] }} (hPanel → Domains → DNS / Nameservers) — needed for SPF, DKIM and DMARC.</li>
        <li><strong>20–30 minutes</strong>, plus up to a few hours for DNS to update.</li>
    </ul>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <h3>A. Enter the mailbox details</h3>
    <p><strong>Easiest:</strong> <x-docs.admin-link route="filament.admin.pages.platform-email-settings">Site → Platform Email</x-docs.admin-link> → fill in the server, mailbox address and mailbox password → <x-docs.ui>Send test email</x-docs.ui> → when it arrives, switch on <x-docs.ui>Use these settings instead of the server .env</x-docs.ui> → <x-docs.ui>Save changes</x-docs.ui>. Whenever you change the mailbox password in hPanel, update it there too.</p>
    <p>Or put them in the server .env. Hostinger Email settings: outgoing server <code>smtp.hostinger.com</code>, port <code>465</code> with SSL (or <code>587</code> with STARTTLS). Username is the full email address.</p>
    @include('filament.pages.documentation.guides._env', ['lines' => [
        'MAIL_MAILER=smtp',
        'MAIL_SCHEME=smtps',
        'MAIL_HOST=smtp.hostinger.com',
        'MAIL_PORT=465',
        'MAIL_USERNAME=hello@'.$urls['root_domain'],
        'MAIL_PASSWORD="your-mailbox-password"',
        'MAIL_FROM_ADDRESS=hello@'.$urls['root_domain'],
        'MAIL_FROM_NAME="AffilStack"',
    ]])
    <x-docs.callout type="warning" title="The From address must be the same mailbox (or an alias of it)">
        Hostinger rejects or spam-folders mail whose From address isn't the mailbox you logged in with. Keep <code>MAIL_USERNAME</code> and <code>MAIL_FROM_ADDRESS</code> the same unless you've created an alias.
    </x-docs.callout>

    <h3>Using a different provider instead</h3>
    <div class="afs-g-table-wrap">
        <table class="afs-g-table">
            <thead><tr><th>Provider</th><th>MAIL_HOST</th><th>Port / scheme</th><th>Password to use</th></tr></thead>
            <tbody>
                <tr><td>Hostinger Email</td><td><code>smtp.hostinger.com</code></td><td>465 · <code>smtps</code></td><td>Mailbox password</td></tr>
                <tr><td>Gmail / Google Workspace</td><td><code>smtp.gmail.com</code></td><td>465 · <code>smtps</code></td><td>16-character <x-docs.ext href="https://myaccount.google.com/apppasswords">App Password</x-docs.ext> (needs 2-Step Verification). Personal Gmail is limited to about 500 recipients a day — fine for testing, too low for a growing platform.</td></tr>
                <tr><td>Zoho Mail (custom domain)</td><td><code>smtppro.zoho.com</code> (<code>smtp.zoho.com</code> for free @zoho.com; <code>.eu</code>/<code>.in</code> for those data centres)</td><td>465 · <code>smtps</code></td><td>Account password, or an app-specific password if 2FA is on</td></tr>
                <tr><td>Microsoft 365</td><td><code>smtp.office365.com</code></td><td>587 · <code>smtp</code></td><td>Not recommended: Microsoft switches password-based SMTP off by default at the end of December 2026.</td></tr>
            </tbody>
        </table>
    </div>

    <h3>B. Prove to Gmail & Yahoo that the mail is really yours (SPF, DKIM, DMARC)</h3>
    <p>Since 2024 Gmail and Yahoo require every sender to pass SPF or DKIM, and bulk senders all three. Without them, password-reset emails land in spam.</p>
    <x-docs.steps>
        <x-docs.step title="Let Hostinger add the records (easiest)">
            <p>hPanel → <x-docs.ui>Emails</x-docs.ui> → click your domain's mailboxes → left menu <x-docs.ui>Domain settings</x-docs.ui> (sometimes labelled DNS settings). If {{ $urls['root_domain'] }} uses Hostinger nameservers, click <x-docs.ui>Connect automatically</x-docs.ui> — it adds MX, SPF and DKIM for you. Then skip to DMARC.</p>
        </x-docs.step>
        <x-docs.step title="SPF (if adding manually)">
            <p>DNS → add a <strong>TXT</strong> record, Name/Host <code>@</code>:</p>
            <x-docs.copy value="v=spf1 include:_spf.mail.hostinger.com ~all" label="TXT value" />
            <p>There must be only <strong>one</strong> SPF record. If one exists already (e.g. for Google Workspace), merge them into one line: <code>v=spf1 include:_spf.mail.hostinger.com include:_spf.google.com ~all</code>.</p>
        </x-docs.step>
        <x-docs.step title="DKIM (if adding manually)">
            <p>Add three <strong>CNAME</strong> records:</p>
            <div class="afs-g-table-wrap">
                <table class="afs-g-table">
                    <thead><tr><th>Name / Host</th><th>Points to</th></tr></thead>
                    <tbody>
                        <tr><td><code>hostingermail-a._domainkey</code></td><td><code>hostingermail-a.dkim.mail.hostinger.com</code></td></tr>
                        <tr><td><code>hostingermail-b._domainkey</code></td><td><code>hostingermail-b.dkim.mail.hostinger.com</code></td></tr>
                        <tr><td><code>hostingermail-c._domainkey</code></td><td><code>hostingermail-c.dkim.mail.hostinger.com</code></td></tr>
                    </tbody>
                </table>
            </div>
        </x-docs.step>
        <x-docs.step title="DMARC">
            <p>Add a <strong>TXT</strong> record with Name/Host <code>_dmarc</code>. Start in monitoring mode:</p>
            <x-docs.copy :value="'v=DMARC1; p=none; rua=mailto:hello@'.$urls['root_domain']" label="TXT value" />
            <p>After 2–4 weeks of clean reports, tighten it to <code>p=quarantine</code>.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>Log out, open <a href="{{ $urls['login'] }}" target="_blank" rel="noopener">the sign-in page</a> → <strong>Forgot password</strong> → enter your own address. The email should arrive in your <strong>inbox</strong> within a minute (emails are queued and sent by the every-minute cron job).</li>
        <li>In Gmail, open it → <x-docs.ui>⋮</x-docs.ui> → <x-docs.ui>Show original</x-docs.ui>: SPF, DKIM and DMARC should all say <strong>PASS</strong>.</li>
        <li>The <strong>Platform email</strong> card on the checklist shows "Set up" once <code>MAIL_MAILER</code> is no longer <code>log</code>.</li>
        <li>Optional: send a test to <x-docs.ext href="https://www.mail-tester.com/">mail-tester.com</x-docs.ext> and aim for 9/10 or better.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="Nothing arrives at all">
        <p>Check <code>MAIL_MAILER</code> isn't <code>log</code>, that the cron job runs every minute (queued mail is sent by <code>queue:work</code>), and look in <code>storage/logs/laravel.log</code> for "Connection could not be established" or "Authentication failed". Did you delete <code>bootstrap/cache/config.php</code> after editing .env?</p>
    </x-docs.trouble>
    <x-docs.trouble title="535 Authentication failed">
        <p>Wrong mailbox password, or the username isn't the full address. Reset the mailbox password in hPanel → Emails and paste it again (wrap it in quotes in .env if it contains <code>#</code> or spaces).</p>
    </x-docs.trouble>
    <x-docs.trouble title="Gmail says &quot;Application-specific password required&quot; / &quot;Use an App Password&quot;">
        <p>Gmail never accepts your normal password over SMTP. Turn on 2-Step Verification, then create an App Password at <x-docs.ext href="https://myaccount.google.com/apppasswords">myaccount.google.com/apppasswords</x-docs.ext> and use the 16 characters (no spaces) as <code>MAIL_PASSWORD</code>.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Emails land in spam">
        <p>Check "Show original" for a failing SPF/DKIM/DMARC; make sure the From address is on your own domain (not @gmail.com) and that there is only one SPF record.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Connection timed out on port 465 or 587">
        <p>Try the other port with the matching scheme (<code>465</code> + <code>smtps</code>, or <code>587</code> + <code>smtp</code>). Hostinger's own servers allow both.</p>
    </x-docs.trouble>
</x-docs.section>
