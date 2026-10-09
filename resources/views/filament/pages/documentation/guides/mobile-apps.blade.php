<x-docs.section id="before" title="Before you start" icon="heroicon-o-clipboard-document-check">
    <p>When your AffilStack apps are live, AffilStack shows a "Get the app" band on the homepage, store badges in the footer and (with an App Store link) Safari's Smart App Banner on iPhones.</p>
    <ul>
        <li><strong>Google Play developer account</strong> — US$25 one-time (<x-docs.ext href="https://play.google.com/console/signup">play.google.com/console</x-docs.ext>). New personal accounts must run a closed test with testers before going to production.</li>
        <li><strong>Apple Developer Program</strong> — US$99 a year (<x-docs.ext href="https://developer.apple.com/programs/enroll/">developer.apple.com</x-docs.ext>); organizations need a D-U-N-S number.</li>
        <li>The apps themselves are built and submitted by your developer — this guide only covers connecting the published listings.</li>
    </ul>
</x-docs.section>

<x-docs.section id="steps" title="Step-by-step" icon="heroicon-o-list-bullet">
    <x-docs.steps>
        <x-docs.step title="Copy the Google Play link">
            <p>Play Console → your app → <x-docs.ui>View on Google Play</x-docs.ui> → copy the address (looks like <code>https://play.google.com/store/apps/details?id=com.affilstack.app</code>).</p>
        </x-docs.step>
        <x-docs.step title="Copy the App Store link">
            <p>App Store Connect → your app → <x-docs.ui>View on App Store</x-docs.ui> → copy the address (looks like <code>https://apps.apple.com/app/affilstack/id1234567890</code> — the <code>id…</code> number turns on the Smart App Banner).</p>
        </x-docs.step>
        <x-docs.step title="Paste them">
            <p><x-docs.admin-link route="filament.admin.pages.mobile-app-settings">Website → Mobile Apps</x-docs.admin-link> → <strong>Google Play link</strong> and <strong>Apple App Store link</strong>. Before launch you can switch on <x-docs.ui>Show "Coming soon" badges before launch</x-docs.ui>. Add the app name, pitch and a 1080×1920 screenshot → <x-docs.ui>Save changes</x-docs.ui>.</p>
        </x-docs.step>
    </x-docs.steps>
</x-docs.section>

<x-docs.section id="confirm" title="How to confirm it works" icon="heroicon-o-check-badge">
    <ul>
        <li>Click <x-docs.ui>Preview homepage</x-docs.ui> on the Mobile Apps page — the apps band shows both badges, and they open the right listings.</li>
        <li>The checklist card shows <strong>Working</strong> when both links are saved.</li>
    </ul>
</x-docs.section>

<x-docs.section id="troubleshooting" title="Troubleshooting" icon="heroicon-o-wrench-screwdriver">
    <x-docs.trouble title="The link is rejected">
        <p>Use the public store address (play.google.com / apps.apple.com), not the Play Console or App Store Connect address.</p>
    </x-docs.trouble>
    <x-docs.trouble title="No Smart App Banner on iPhone">
        <p>The App Store link must contain the <code>id…</code> number, and the banner only shows in Safari to people who don't have the app.</p>
    </x-docs.trouble>
</x-docs.section>
