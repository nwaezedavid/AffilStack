<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>Reply detection logs in to the customer's inbox over IMAP every few minutes (on the cron), matches replies from CRM contacts, stops their follow-ups and drafts an AI reply. Customers switch it on themselves on <a href="{{ $urls['email_sending'] }}" target="_blank" rel="noopener">Email Sending</a> → <strong>Inbox reading (IMAP)</strong>. Nothing to configure on the admin side.</p>
    <ul>
        <li>The customer needs their <strong>email address and password / App Password</strong> — usually the same ones as for SMTP.</li>
        <li><strong>5 minutes.</strong> Free.</li>
    </ul>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step (what the customer does)" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Open Inbox reading (IMAP)">
            <p>Email Sending → <strong>See when contacts reply</strong> → <strong>Inbox reading (IMAP)</strong>.</p>
        </x-docs.step>
        <x-docs.step title="Server settings">
            <p>Picking the provider fills these in:</p>
            <div class="afs-g-table-wrap">
                <table class="afs-g-table">
                    <thead><tr><th>Provider</th><th>IMAP server · port · security</th></tr></thead>
                    <tbody>
                        @foreach (config('crm.presets') as $preset)
                            <tr>
                                <td>{{ $preset['label'] }}</td>
                                <td>@if ($preset['imap']['host'])<code>{{ $preset['imap']['host'] }}</code> · {{ $preset['imap']['port'] }} · {{ $preset['imap']['encryption'] === 'tls' ? 'STARTTLS' : 'SSL/TLS' }}@else from the provider's help pages @endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-docs.step>
        <x-docs.step title="Log in">
            <p>Username = full email address; password as below → <x-docs.ui>Turn on reply detection</x-docs.ui>. AffilStack checks the login immediately.</p>
        </x-docs.step>
    </x-docs.steps>

    <h3>Which password, per provider (October 2026)</h3>
    <div class="afs-g-table-wrap">
        <table class="afs-g-table">
            <thead><tr><th>Provider</th><th>Works?</th><th>What to do</th></tr></thead>
            <tbody>
                <tr><td>Gmail / Google Workspace</td><td>Yes</td><td>Turn on <x-docs.ext href="https://myaccount.google.com/signinoptions/twosv">2-Step Verification</x-docs.ext>, then create an <x-docs.ext href="https://myaccount.google.com/apppasswords">App Password</x-docs.ext> (name it "AffilStack") and paste the 16 letters without spaces. IMAP is always on in Gmail; for Workspace the admin must allow IMAP and App Passwords.</td></tr>
                <tr><td>Hostinger Email</td><td>Yes</td><td>Mailbox password (<code>imap.hostinger.com</code>, 993, SSL).</td></tr>
                <tr><td>Zoho Mail</td><td>Yes</td><td>First enable IMAP: Zoho Mail → <x-docs.ui>Settings</x-docs.ui> → <x-docs.ui>Mail Accounts</x-docs.ui> → <x-docs.ui>IMAP</x-docs.ui> → IMAP Access on. With 2FA, use an app-specific password (accounts.zoho.com → Security → App Passwords). Paid custom-domain accounts: choose "Other" and use <code>imappro.zoho.com</code>.</td></tr>
                <tr><td>Yahoo Mail</td><td>Yes</td><td>Account security → <strong>Generate app password</strong>.</td></tr>
                <tr><td>Outlook.com / Hotmail</td><td><strong>No</strong></td><td>Microsoft removed password (basic) logins for third-party apps on 16 Sep 2024; only "Sign in with Microsoft" (OAuth) works, which AffilStack doesn't offer yet.</td></tr>
                <tr><td>Microsoft 365 (work)</td><td><strong>No</strong></td><td>Exchange Online turned off basic authentication for IMAP in 2022; an app password doesn't help. Use another mailbox for replies.</td></tr>
            </tbody>
        </table>
    </div>
    <x-docs.callout type="tip" title="Gmail customers who don't want an App Password">
        Once you've completed <x-docs.guide-link key="gmail-inbox-reading">Gmail inbox reading (CASA)</x-docs.guide-link>, Gmail customers can use "Allow inbox reading" instead of IMAP.
    </x-docs.callout>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>After saving, the card shows <strong>Inbox reading (IMAP)</strong> as on, with "last checked … ago" updating every few minutes.</li>
        <li>Reply to a campaign email from a test contact; within ~5 minutes it shows in the CRM Inbox and that contact's follow-ups stop.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="Gmail: [ALERT] Application-specific password required / Invalid credentials">
        <p>The normal Gmail password was used. Create an App Password (needs 2-Step Verification). If the App Passwords page says the setting isn't available, the account uses only security keys, has Advanced Protection, or is a work account whose admin blocks it.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Microsoft: AUTHENTICATE failed / LOGIN failed">
        <p>Expected — Microsoft no longer accepts passwords over IMAP (see table). Suggest a Gmail, Zoho or Hostinger mailbox.</p>
    </x-docs.trouble>
    <x-docs.trouble title="It worked, then stopped">
        <p>The password or App Password was changed/revoked (changing a Google password revokes all App Passwords). Re-enter it. Also check the cron job is running — reply checks run from <code>schedule:run</code>.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Connection timed out">
        <p>Use port 993 with SSL/TLS; check the server name.</p>
    </x-docs.trouble>
</x-docs.section>
