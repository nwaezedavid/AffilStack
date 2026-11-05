<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <ul>
        <li><strong>Google Places first.</strong> Lead finding already works with the Google Places key alone: it finds local businesses and reads the contact emails on their websites. Apollo and Hunter are optional extras.</li>
        <li><strong>An Apollo and/or Hunter account</strong> on a plan with API access.</li>
        <li><strong>About 10 minutes each.</strong></li>
    </ul>
    <x-docs.callout type="cost" title="What it costs you">
        <p>Apollo charges one email credit for each person whose business email it reveals; searching is free. Hunter charges per domain search (1 credit for up to 10 emails) and half a credit per email check. Customers pay you through their plan's monthly lead allowance, then {{ (int) config('credits.costs.lead_found') + (int) config('credits.costs.lead_email_checked') }} AffilStack credits per extra lead — set each plan's allowance in Billing → Plans so it covers your provider costs.</p>
    </x-docs.callout>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Apollo: create a master API key">
            <p>Sign in at apollo.io → <x-docs.ui>Settings</x-docs.ui> → <x-docs.ui>Integrations</x-docs.ui> → <x-docs.ui>API</x-docs.ui> → <x-docs.ui>Connect</x-docs.ui> → <x-docs.ui>API keys</x-docs.ui> → <x-docs.ui>Create new key</x-docs.ui>. Name it <code>AffilStack</code> and switch on <strong>Set as master key</strong> (people search needs it). Copy the key.</p>
        </x-docs.step>
        <x-docs.step title="Hunter: copy your API key">
            <p>Sign in at hunter.io → click your avatar → <x-docs.ui>API</x-docs.ui> → copy the <strong>secret key</strong>.</p>
        </x-docs.step>
        <x-docs.step title="Paste and test in AffilStack">
            <p>Admin → <x-docs.ui>Site</x-docs.ui> → <x-docs.ui>Lead sources</x-docs.ui>. Paste each key, tick <strong>Use Apollo</strong> / <strong>Use Hunter</strong>, then press <x-docs.ui>Test Apollo</x-docs.ui> and <x-docs.ui>Test Hunter</x-docs.ui>. Each test saves the page and shows whether the key works.</p>
        </x-docs.step>
        <x-docs.step title="Set each plan's lead allowance">
            <p>Admin → <x-docs.ui>Billing</x-docs.ui> → <x-docs.ui>Plans</x-docs.ui> → edit a plan → <strong>B2B leads included per month</strong> (0 = credits only, -1 = unlimited).</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="how" title="How customers use it" icon="heroicon-o-user-group">
    <p>On the research form, customers tick <strong>Also find B2B buyers (leads)</strong> beside the affiliate link field, or press <strong>Find leads</strong> on any product page. AffilStack searches Apollo (decision-makers by the job titles from the product research), then Google Maps businesses, reads each website for its public contact email, fills gaps with Hunter, checks every email, scores how well each lead fits, tags it and saves it to the product's CRM list, ready to export as CSV. Results from paid providers are business emails only, and every lead records where it came from.</p>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ol>
        <li>The <x-docs.ui>Test Apollo</x-docs.ui> and <x-docs.ui>Test Hunter</x-docs.ui> buttons both say <strong>Connected</strong>.</li>
        <li>Open any researched product as a customer and press <strong>Find leads</strong> (10 leads). Within a few minutes the "Last search" line shows the leads found, with Apollo or Hunter listed as sources, and the leads appear in the product's CRM list with a fit score and tags.</li>
    </ol>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <ul>
        <li><strong>"This Apollo API key isn't allowed to use this endpoint"</strong> — the key isn't a master key. Create a new one with <strong>Set as master key</strong> switched on.</li>
        <li><strong>"Hunter's rate limit or monthly quota was reached"</strong> — your Hunter plan's searches are used up. Lead finding carries on with website emails and DNS checks until the quota resets.</li>
        <li><strong>Leads found but few emails</strong> — switch on Hunter: it fills in emails for companies whose website doesn't list one, and verifies every email.</li>
        <li><strong>"Lead finding is being set up"</strong> shown to customers — neither the Google Places key nor Apollo is set. Add at least one.</li>
    </ul>
</x-docs.section>
