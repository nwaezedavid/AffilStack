<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>GitHub Sync pushes AffilStack's own code to a private GitHub repository every day and whenever you click "Sync now" — an off-site backup and a hand-off point for developers. Nothing customer-facing depends on it.</p>
    <ul>
        <li><strong>A GitHub account</strong> and a <strong>private</strong> repository (e.g. <code>affilstack</code>) — never public: the code contains business logic.</li>
        <li><strong>10 minutes.</strong> Free.</li>
    </ul>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Create the repository">
            <p>github.com → <x-docs.ui>New repository</x-docs.ui> → name <code>affilstack</code> → <x-docs.ui>Private</x-docs.ui> → <x-docs.ui>Create repository</x-docs.ui>.</p>
        </x-docs.step>
        <x-docs.step title="Create a fine-grained token">
            <p>GitHub → your avatar → <x-docs.ui>Settings</x-docs.ui> → <x-docs.ui>Developer settings</x-docs.ui> → <x-docs.ui>Personal access tokens</x-docs.ui> → <x-docs.ui>Fine-grained tokens</x-docs.ui> → <x-docs.ui>Generate new token</x-docs.ui>. Name <code>AffilStack sync</code>, expiration up to a year (set a reminder), <strong>Repository access</strong>: Only select repositories → <code>affilstack</code>. <strong>Permissions</strong> → Repository → <strong>Contents: Read and write</strong> (Metadata: read-only is added automatically) → <x-docs.ui>Generate token</x-docs.ui> → copy it (starts with <code>github_pat_</code>, shown once).</p>
        </x-docs.step>
        <x-docs.step title="Paste it">
            <p><x-docs.admin-link route="filament.admin.pages.git-hub-sync-settings">System → GitHub Sync</x-docs.admin-link> → <strong>Repository owner</strong> (your GitHub username), <strong>Repository name</strong> (<code>affilstack</code>), <strong>Target branch</strong> (e.g. <code>main</code>), <strong>Personal access token</strong>. Turn on <x-docs.ui>Auto-sync enabled</x-docs.ui> for daily pushes → <x-docs.ui>Save changes</x-docs.ui> → <x-docs.ui>Verify connection</x-docs.ui> → <x-docs.ui>Sync now</x-docs.ui>.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>Sync now reports success and the repository on GitHub shows the code with a fresh commit; the checklist card turns <strong>Working</strong>.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="401 Bad credentials">
        <p>The token expired or was deleted. Create a new one and paste it.</p>
    </x-docs.trouble>
    <x-docs.trouble title="403 / 404 Resource not accessible by personal access token">
        <p>The token doesn't include this repository or lacks <strong>Contents: Read and write</strong>; or the owner/name fields have a typo.</p>
    </x-docs.trouble>
    <x-docs.trouble title="Need help reading an error">
        <p>Use <x-docs.ui>Explain with AI</x-docs.ui> on the GitHub Sync page.</p>
    </x-docs.trouble>
</x-docs.section>
