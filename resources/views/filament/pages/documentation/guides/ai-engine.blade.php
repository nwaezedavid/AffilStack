<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>AffilStack writes everything with one "main" AI engine — <strong>OpenAI</strong> or <strong>Claude</strong> — and can fall back to the other if the first fails. <strong>Images always use OpenAI</strong> (model <code>{{ config('ai.openai.image_model') }}</code>), because Claude doesn't generate images. So you need an OpenAI key in every case, and a Claude key if you want Claude to write.</p>
    <ul>
        <li><strong>OpenAI account</strong> at <x-docs.ext href="https://platform.openai.com/">platform.openai.com</x-docs.ext> with prepaid credit.</li>
        <li><strong>Optional: Anthropic (Claude) account</strong> at <x-docs.ext href="https://platform.claude.com/">platform.claude.com</x-docs.ext> (the old console.anthropic.com address redirects there) with prepaid credit — at least US$5 to activate the API.</li>
        <li><strong>A card</strong> for both. Both bill per use; set a monthly spending limit so a busy month can't surprise you.</li>
    </ul>
    <x-docs.callout type="danger" title="Can I use my Claude Pro/Max subscription instead of an API key?" big>
        <p><strong>No.</strong> A Claude.ai subscription (Free, Pro, Max, Team) covers only your own use of Anthropic's apps — Claude.ai, the desktop/mobile apps and Claude Code. Anthropic's terms don't allow the login or OAuth tokens from a subscription to power any other product, tool or service, and Anthropic clarified this in its documentation in February 2026 after blocking such use in third-party apps. AffilStack serves your customers, so it must use a pay-as-you-go <strong>API key</strong> from the Claude Console.</p>
        <p>Sources: <x-docs.ext href="https://code.claude.com/docs/en/legal-and-compliance">Anthropic — Legal and compliance (authentication &amp; subscriptions)</x-docs.ext> · <x-docs.ext href="https://www.anthropic.com/legal/consumer-terms">Anthropic Consumer Terms</x-docs.ext>. You can of course keep using your subscription yourself to plan content or manage AffilStack.</p>
    </x-docs.callout>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <h3>A. OpenAI (required — text and images)</h3>
    <x-docs.steps>
        <x-docs.step title="Add credit">
            <p>platform.openai.com → <x-docs.ui>Settings</x-docs.ui> (gear) → <x-docs.ui>Billing</x-docs.ui> → <x-docs.ui>Add to credit balance</x-docs.ui>. Under <x-docs.ui>Limits</x-docs.ui>, set a monthly budget alert.</p>
        </x-docs.step>
        <x-docs.step title="Verify your organization (needed for image generation)">
            <p><x-docs.ui>Settings</x-docs.ui> → <x-docs.ui>Organization</x-docs.ui> → <x-docs.ui>General</x-docs.ui> → <x-docs.ui>Verify Organization</x-docs.ui>. It's an ID check (passport or ID + selfie) run by OpenAI's partner. OpenAI's latest image models are only available to verified organizations — without it, image generation fails while text still works.</p>
        </x-docs.step>
        <x-docs.step title="Create the API key">
            <p><x-docs.ext href="https://platform.openai.com/api-keys">platform.openai.com/api-keys</x-docs.ext> → <x-docs.ui>Create new secret key</x-docs.ui> → name <code>AffilStack</code>, permissions <strong>All</strong> → copy the key (<code>sk-…</code>). It's shown only once.</p>
        </x-docs.step>
        <x-docs.step title="Put it in the server .env">
            <p>The OpenAI key lives in .env, not in the admin panel:</p>
            @include('filament.pages.documentation.guides._env', ['lines' => [
                'OPENAI_API_KEY=sk-your-key',
                'OPENAI_TEXT_MODEL='.config('ai.openai.text_model'),
                'OPENAI_IMAGE_MODEL='.config('ai.openai.image_model'),
            ]])
            <p class="afs-g-small">The model lines are optional — those are the current defaults.</p>
        </x-docs.step>
    </x-docs.steps>

    <h3>B. Claude by Anthropic (optional — main writer or fallback)</h3>
    <x-docs.steps>
        <x-docs.step title="Add credit">
            <p><x-docs.ext href="https://platform.claude.com/">platform.claude.com</x-docs.ext> → <x-docs.ui>Settings</x-docs.ui> → <x-docs.ui>Billing</x-docs.ui> → add a card and buy credits (minimum US$5). Under <x-docs.ui>Limits</x-docs.ui> set a monthly spend limit.</p>
        </x-docs.step>
        <x-docs.step title="Create the API key">
            <p>Left menu <x-docs.ui>API Keys</x-docs.ui> → <x-docs.ui>Create Key</x-docs.ui> → name <code>AffilStack</code> → copy it (starts with <code>sk-ant-</code>; shown once).</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="paste" title="Where to paste in AffilStack" icon="heroicon-o-clipboard">
    <x-docs.steps>
        <x-docs.step>
            <p>Open <x-docs.admin-link route="filament.admin.pages.ai-engine-settings">AI Agents → AI Engine (OpenAI / Claude)</x-docs.admin-link>.</p>
        </x-docs.step>
        <x-docs.step>
            <p><strong>Main AI engine</strong>: OpenAI or Claude. Paste the <strong>Claude API key</strong> if you use Claude and pick a <strong>Claude model</strong>:</p>
            <ul>
                @foreach (\App\Filament\Pages\AiEngineSettings::CLAUDE_MODELS as $model => $label)
                    <li><code>{{ $model }}</code> — {{ \Illuminate\Support\Str::after($label, '— ') }}</li>
                @endforeach
            </ul>
        </x-docs.step>
        <x-docs.step>
            <p>Keep <strong>"If the main engine fails, automatically try the other one"</strong> on when both keys are set — it keeps customers working during an outage or when one account runs out of credit. Click <x-docs.ui>Save changes</x-docs.ui>.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>On the AI Engine page click <x-docs.ui>Test OpenAI</x-docs.ui> and (if used) <x-docs.ui>Test Claude</x-docs.ui> — each section's status changes to "✓ Working".</li>
        <li>The checklist card turns <strong>Working</strong> once the main engine has passed its test.</li>
        <li>From a customer account, run one offer research and generate one image.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="401 / &quot;invalid x-api-key&quot; / &quot;Incorrect API key provided&quot;">
        <p>The key was copied incompletely, revoked, or (OpenAI) the .env change hasn't been picked up — delete <code>bootstrap/cache/config.php</code>.</p>
    </x-docs.trouble>
    <x-docs.trouble title="429 / &quot;insufficient_quota&quot; / &quot;credit balance is too low&quot;">
        <p>The account is out of prepaid credit or hit its spend limit. Top up; with fallback on, the other engine covers text in the meantime.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Images fail but text works">
        <p>The OpenAI organization isn't verified for the image model, or <code>OPENAI_API_KEY</code> is missing (images never use Claude).</p>
    </x-docs.trouble>
    <x-docs.trouble title="Claude 404 &quot;model not found&quot;">
        <p>Anthropic retired or renamed the model. Choose another model on the AI Engine page and press Test Claude.</p>
    </x-docs.trouble>
</x-docs.section>
