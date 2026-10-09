{{-- Shared: how to put values into the server .env file on Hostinger. Pass $lines (array of "KEY=value" strings). --}}
<x-docs.steps>
    <x-docs.step title="Open File Manager">
        <p>In Hostinger hPanel go to <x-docs.ui>Websites</x-docs.ui> → <x-docs.ui>{{ $urls['root_domain'] }}</x-docs.ui> → <x-docs.ui>Dashboard</x-docs.ui> → <x-docs.ui>File Manager</x-docs.ui>.</p>
    </x-docs.step>
    <x-docs.step title="Find the AffilStack app folder">
        <p>It's the folder that contains <code>artisan</code>, <code>app</code>, <code>config</code> and <code>.env</code> — the folder that <code>public_html</code> points to (its <code>public</code> sub-folder is what visitors see). Don't confuse it with <code>public_html</code> itself.</p>
        <p class="afs-g-small">Can't see <code>.env</code>? It's a hidden file — turn on "Show hidden files" in File Manager's settings.</p>
    </x-docs.step>
    <x-docs.step title="Edit .env and add or change these lines">
        @foreach ($lines as $line)
            <x-docs.copy :value="$line" />
        @endforeach
        <p>If a line already exists, change its value instead of adding a second copy. Values with spaces need quotes, e.g. <code>MAIL_FROM_NAME="AffilStack"</code>. Save the file.</p>
    </x-docs.step>
    <x-docs.step title="Make the app re-read .env">
        <p>Open the <code>bootstrap/cache</code> folder inside the app folder. If it contains <code>config.php</code>, delete that one file (it's a cached copy of the settings and is safe to delete). If you use SSH instead, run <code>php artisan config:clear</code> in the app folder. No <code>config.php</code> there? Nothing to do — changes apply on the next page load.</p>
    </x-docs.step>
</x-docs.steps>
<x-docs.callout type="danger" title="Never share or commit .env">
    It holds every password and API key. Don't paste it into chats or tickets, and keep a private backup copy before editing.
</x-docs.callout>
