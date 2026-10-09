<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>Nothing to configure on the admin side — each customer connects their own mailbox on <a href="{{ $urls['email_sending'] }}" target="_blank" rel="noopener">Email Sending</a> → <strong>Business email (SMTP)</strong>. This guide is what you (or support) send customers who get stuck, and how to test it yourself.</p>
    <ul>
        <li>The customer needs their <strong>email address, its password or App Password</strong>, and their provider's SMTP server (AffilStack fills it in when they pick a provider).</li>
        <li><strong>5 minutes</strong> per customer. Free.</li>
    </ul>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step (what the customer does)" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Open Email Sending">
            <p>Dashboard → <strong>Email Sending</strong> → <strong>Business email (SMTP)</strong>.</p>
        </x-docs.step>
        <x-docs.step title="Pick the provider">
            <p><strong>Your email provider</strong>: choose from the list — AffilStack fills in the server, port and security:</p>
            <div class="afs-g-table-wrap">
                <table class="afs-g-table">
                    <thead><tr><th>Provider</th><th>SMTP server · port · security</th><th>Password to use</th></tr></thead>
                    <tbody>
                        @foreach (config('crm.presets') as $key => $preset)
                            <tr>
                                <td>{{ $preset['label'] }}</td>
                                <td>@if ($preset['smtp']['host'])<code>{{ $preset['smtp']['host'] }}</code> · {{ $preset['smtp']['port'] }} · {{ strtoupper($preset['smtp']['encryption'] === 'tls' ? 'STARTTLS' : 'SSL/TLS') }}@else from the provider's help pages @endif</td>
                                <td>{{ $preset['help'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-docs.step>
        <x-docs.step title="Fill in the rest">
            <p><strong>Username</strong> = the full email address. <strong>Password</strong> = see the table. <strong>Send from (email)</strong> = the same address. Click <x-docs.ui>Save &amp; verify</x-docs.ui> — AffilStack logs in to the server and shows the result.</p>
        </x-docs.step>
        <x-docs.step title="Send a test">
            <p>Click <x-docs.ui>Test connection</x-docs.ui>. Then set a sensible <strong>Daily sending limit</strong> and the footer details (business name and postal address — required by anti-spam laws) under <strong>Sending limits &amp; email footer</strong>.</p>
        </x-docs.step>
    </x-docs.steps>

    <h3>Provider notes (October 2026)</h3>
    <ul>
        <li><strong>Gmail / Google Workspace</strong>: needs 2-Step Verification and a 16-character <x-docs.ext href="https://myaccount.google.com/apppasswords">App Password</x-docs.ext>; the normal password is always rejected. Some work/school accounts can't create App Passwords — those customers should use <x-docs.guide-link key="gmail-sending">Connect Gmail</x-docs.guide-link>.</li>
        <li><strong>Hostinger Email</strong>: mailbox password from hPanel → Emails. Works on 465/SSL or 587/STARTTLS.</li>
        <li><strong>Zoho Mail</strong>: the preset uses <code>smtp.zoho.com</code>, which is right for free/personal Zoho accounts. Paid accounts on their own domain must use <strong>Other / custom server</strong> with <code>smtppro.zoho.com</code> (465 SSL). EU/India data centres use the server shown in Zoho Mail → Settings → Mail Accounts → server configuration.</li>
        <li><strong>Yahoo</strong>: Account Info → Account security → <strong>Generate app password</strong>.</li>
        <li><strong>Outlook.com / Hotmail (personal)</strong>: Microsoft switched off password logins for third-party apps on 16 September 2024 — it <strong>won't work</strong> with SMTP. Use another provider.</li>
        <li><strong>Microsoft 365 (work)</strong>: works only if the admin has <strong>Authenticated SMTP</strong> enabled for the mailbox. Microsoft disables password-based SMTP by default for existing tenants at the <strong>end of December 2026</strong> (admins can still re-enable it until a final date Microsoft will announce for 2027). Recommend another provider for new customers.</li>
    </ul>

    <h3>Help customers reach the inbox</h3>
    <p>Customers sending from their own domain should add SPF, DKIM and DMARC for it — the same three records described in <x-docs.guide-link key="platform-email">Platform email</x-docs.guide-link>, using their provider's values. Gmail and Yahoo require them for anyone sending in bulk.</p>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>The Email Sending page shows the sender as verified after <x-docs.ui>Save &amp; verify</x-docs.ui> / <x-docs.ui>Test connection</x-docs.ui>.</li>
        <li>Send a one-contact campaign to your own address — it arrives from the customer's address.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="Gmail: &quot;Application-specific password required&quot; / 534-5.7.9 / &quot;Use an App Password&quot;">
        <p>The customer used their normal Gmail password. Turn on 2-Step Verification, create an App Password and paste the 16 characters without spaces.</p>
    </x-docs.trouble>
    <x-docs.trouble title="535 Authentication failed / Username and Password not accepted">
        <p>Wrong password, the username isn't the full address, or (Zoho/Yahoo) an app-specific password is required because 2FA is on.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Microsoft: &quot;SmtpClientAuthentication is disabled for the Tenant&quot; / 5.7.139">
        <p>The Microsoft 365 admin must enable Authenticated SMTP for that mailbox (Microsoft 365 admin center → Users → the user → Mail → Manage email apps → Authenticated SMTP). Outlook.com personal accounts can't be fixed — use another provider.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Connection timed out">
        <p>Try the other port/security pair (465 + SSL/TLS or 587 + STARTTLS) and double-check the server name (e.g. <code>smtppro.zoho.com</code> for paid Zoho).</p>
    </x-docs.trouble>
</x-docs.section>
