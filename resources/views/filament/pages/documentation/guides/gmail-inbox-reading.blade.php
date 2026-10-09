<x-docs.callout type="warning" title="Keep the toggle OFF until Google approves CASA" big>
    <p>The <strong>"Allow Gmail inbox reading — requires Google CASA approval"</strong> toggle adds the restricted <code>gmail.readonly</code> scope to every "Connect Gmail" click. Before Google approves it, customers get blocked or warned and your app can be flagged. Until then, every customer already has free reply detection through <x-docs.guide-link key="imap-replies">IMAP</x-docs.guide-link>.</p>
</x-docs.callout>

<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <ul>
        <li><strong>Gmail sending already verified</strong> — finish <x-docs.guide-link key="gmail-sending">Gmail sending</x-docs.guide-link> first; inbox reading builds on the same verification.</li>
        <li><strong>Budget for a CASA assessment.</strong> <code>gmail.readonly</code> is a Google <strong>restricted</strong> scope: Google requires a Cloud Application Security Assessment (CASA) by an approved lab, and repeats it every year. Typical quotes for the usual Tier 2 assessment run about <strong>US$540–1,800</strong> (TAC Security's basic package is the cheapest commonly cited), 1–4 weeks.</li>
        <li><strong>Someone technical on call</strong> — the lab scans the live app and may ask for security fixes.</li>
        <li><strong>Total time:</strong> often 1–3 months from submission to approval.</li>
    </ul>
    <x-docs.callout type="info" title="Is it worth it?">
        It only saves Gmail customers from creating an App Password for IMAP. For most platforms at launch, IMAP is enough — revisit this when many customers ask for it.
    </x-docs.callout>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Add the restricted scope">
            <p>Google Auth Platform → <x-docs.ui>Data Access</x-docs.ui> → <x-docs.ui>Add or remove scopes</x-docs.ui> → add <code>https://www.googleapis.com/auth/gmail.readonly</code> → <x-docs.ui>Update</x-docs.ui> → <x-docs.ui>Save</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step title="Update the Privacy Policy">
            <p>Extend the Google section of the privacy page: AffilStack reads incoming messages only to detect replies from the user's own CRM contacts, stores only what's needed to show the reply in the CRM inbox, never uses the data for ads or to train general AI models, and follows the Limited Use requirements.</p>
        </x-docs.step>
        <x-docs.step title="Record a new demo video">
            <p>Same as for sending, plus: a contact replies → the reply appears in the AffilStack CRM Inbox and the sequence stops.</p>
        </x-docs.step>
        <x-docs.step title="Submit and justify">
            <p>Verification Center → submit. Justify why a narrower scope can't work (AffilStack must see replies to stop follow-ups; <code>gmail.metadata</code> doesn't include the reply text the AI needs to draft a response).</p>
        </x-docs.step>
        <x-docs.step title="Do the CASA assessment when Google asks">
            <p>Google's reviewer emails you a link to the <x-docs.ext href="https://appdefensealliance.dev/casa">App Defense Alliance CASA</x-docs.ext> process. Pick an authorized lab (e.g. <x-docs.ext href="https://casa.tacsecurity.com/">TAC Security</x-docs.ext>), pay, run their scan against {{ $urls['site'] }}, fix what they find, and receive a <strong>Letter of Validation</strong>. The lab or you submit it to Google.</p>
        </x-docs.step>
        <x-docs.step title="Wait for the approval email">
            <p>Only when Verification Center shows <code>gmail.readonly</code> as approved, continue below. Put a calendar reminder for the yearly re-assessment.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="paste" title="Where to switch it on in AffilStack" icon="heroicon-o-clipboard">
    <p><x-docs.admin-link route="filament.admin.pages.google-o-auth-settings">Site → Google Login</x-docs.admin-link> → turn on <x-docs.ui>Allow Gmail inbox reading — requires Google CASA approval</x-docs.ui> → <x-docs.ui>Save changes</x-docs.ui>. Customers who connected Gmail earlier see an <strong>Allow inbox reading</strong> button on their Email Sending page and must click it once.</p>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>A customer's Email Sending page shows "Gmail inbox reading" as active (no App Password needed).</li>
        <li>Reply to a campaign email from a test contact — within a few minutes it shows in the CRM Inbox and that contact's follow-ups stop.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="Customers see &quot;This app is blocked&quot; after connecting Gmail">
        <p>The toggle is on but the restricted scope isn't approved. Turn the toggle off immediately — sending keeps working.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Google asks for CASA again a year later">
        <p>Restricted-scope apps are re-assessed every 12 months. Start early so the approval doesn't lapse.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Replies aren't detected for an older Gmail connection">
        <p>That customer connected before reading was enabled — they must click "Allow inbox reading" once.</p>
    </x-docs.trouble>
</x-docs.section>
