@extends('layouts.app')

@section('title', 'Connected Accounts')

@section('content')
    @php
        $lastChecked = $connections->pluck('last_verified_at')->filter()->sortDesc()->first();
        $broken = $connections->filter(fn ($c) => $c->isBroken());
    @endphp

    <x-ui.page-header
        eyebrow="Connections"
        title="Connected Accounts"
        subtitle="Link your social accounts once, and AffilStack can publish your videos and export your content straight to them. Every connection is tested live, so you always know it works."
        icon="link"
        tone="violet"
    >
        <x-slot:actions>
            <x-ui.button variant="light" :href="route('api-access.index')" icon="code-bracket-square">API & Integrations</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 mb-6">
        <x-ui.stat label="Connected" :value="$summary['connected'].' of '.$providers->count()" icon="link" tone="violet" hint="accounts linked" />
        <x-ui.stat label="Verified" :value="$summary['verified']" icon="check-badge" tone="emerald" hint="passed their last check" />
        <x-ui.stat label="Need attention" :value="$summary['attention']" icon="exclamation-triangle" :tone="$summary['attention'] ? 'rose' : 'brand'" hint="reconnect or re-test" />
        <x-ui.stat label="Last checked" :value="$lastChecked ? $lastChecked->diffForHumans(short: true) : '—'" icon="clock" tone="gold" hint="re-checked daily" />
    </div>

    @if ($broken->isNotEmpty())
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-orange-200 bg-orange-50 p-4" role="alert">
            <x-heroicon-s-exclamation-triangle class="h-5 w-5 shrink-0 text-orange-600" />
            <div class="text-sm text-orange-900">
                <p class="font-semibold">{{ $broken->count() === 1 ? 'One connection needs' : $broken->count().' connections need' }} your attention</p>
                <p class="mt-0.5">
                    {{ $broken->map(fn ($c) => $providers[$c->provider]['label'] ?? ucfirst($c->provider))->implode(', ') }}
                    — publishing and exporting to {{ $broken->count() === 1 ? 'it' : 'them' }} won't work until you reconnect or the next check passes.
                </p>
            </div>
        </div>
    @endif

    <x-ui.feature-intro class="mb-6" :open="$connections->isEmpty()" :steps="[
        'Pick a platform below and click its button — you approve access on the platform\'s own site.',
        'We test the connection straight away and mark it Verified when it works.',
        'Use the Publish / Export buttons on your generated content — they send it to the right account.',
        'We re-check every connection daily and ping your bell if one ever needs reconnecting.',
    ]">
        Connected Accounts is where AffilStack gets permission to act for you on each platform — only the
        permissions listed on each card, and you can disconnect at any time.
    </x-ui.feature-intro>

    <div class="grid gap-5 lg:grid-cols-2">
        @foreach ($providers as $key => $provider)
            @php
                $connection = $connections->get($key);
                $label = $provider['label'];
            @endphp

            @if (! $connection && ! $provider['available'])
                {{-- Admin hasn't set this platform up yet: a calm placeholder, never a broken button. --}}
                <x-ui.connection-card :brand="$provider['brand']" :name="$label" :description="$provider['tagline']"
                    status="not_connected" status-label="Not available yet" class="opacity-75 hover:translate-y-0 hover:shadow-none">
                    <x-slot:details>
                        <p class="rounded-lg bg-surface-muted px-3 py-2 text-xs text-ink-600">
                            {{ $label }} connections aren't available yet — the AffilStack team is finishing setup with {{ $label }}. Check back soon.
                        </p>
                    </x-slot:details>
                    <span class="inline-flex items-center gap-2 rounded-full bg-surface-muted px-4 py-2.5 text-sm font-medium text-ink-400 ring-1 ring-line cursor-not-allowed" aria-disabled="true">
                        <x-heroicon-m-lock-closed class="h-4 w-4" /> Coming soon
                    </span>
                </x-ui.connection-card>
                @continue
            @endif

            <x-ui.connection-card
                :brand="$provider['brand']"
                :name="$label"
                :description="$provider['tagline']"
                :status="$connection ? $connection->displayStatus() : 'not_connected'"
                :account="$connection?->account_name"
                :avatar="$connection?->avatar_url"
                :checked-at="$connection?->last_verified_at"
                :message="$connection?->verification_message"
            >
                <x-slot:details>
                    @if (! $connection)
                        <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-400 mb-2">What connecting unlocks</p>
                        <ul class="space-y-1.5">
                            @foreach ($provider['enables'] as $benefit)
                                <li class="flex items-start gap-2 text-sm text-ink-900">
                                    <x-heroicon-s-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" /> {{ $benefit }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-3">
                        <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-400 mb-1.5">{{ $connection ? 'Permissions granted' : 'Permissions requested' }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($provider['permissions'] as $permission)
                                <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2.5 py-0.5 text-xs text-violet-700 ring-1 ring-inset ring-violet-600/15">
                                    <x-heroicon-m-key class="h-3 w-3" /> {{ $permission }}
                                </span>
                            @endforeach
                        </div>
                        @if ($provider['via'])
                            <p class="mt-2 flex items-start gap-1.5 text-xs text-ink-600">
                                <x-heroicon-m-information-circle class="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-400" /> {{ $provider['via'] }}
                            </p>
                        @endif
                    </div>

                    @if ($connection && $provider['publishes'])
                        @if ($provider['approved'])
                            <p class="mt-3 flex items-start gap-1.5 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                                <x-heroicon-m-check-badge class="mt-0.5 h-3.5 w-3.5 shrink-0" /> {{ $label }} has approved AffilStack for publishing — your Publish buttons are live.
                            </p>
                        @else
                            <p class="mt-3 flex items-start gap-1.5 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                <x-heroicon-m-clock class="mt-0.5 h-3.5 w-3.5 shrink-0" /> Awaiting {{ $label }}'s approval of AffilStack for publishing. Until then, download your video and post it yourself — your connection is ready the moment approval lands.
                            </p>
                        @endif
                    @endif

                    @if ($connection && ! $provider['available'])
                        <p class="mt-3 rounded-lg bg-surface-muted px-3 py-2 text-xs text-ink-600">{{ $label }} connections are paused on AffilStack right now, so this one can't be tested until they're back.</p>
                    @endif

                    @if ($connection?->connected_at)
                        <p class="mt-3 text-[11px] text-ink-400">Connected {{ $connection->connected_at->format('M j, Y') }}</p>
                    @endif
                </x-slot:details>

                @if (! $connection)
                    <x-ui.oauth-button :brand="$provider['button_brand']" :href="route('social-connections.redirect', $key)"
                        :label="$provider['button_label']" aria-label="Connect {{ $label }} — {{ $provider['button_label'] }}" />
                @else
                    @if ($connection->isBroken() && $provider['available'])
                        <x-ui.oauth-button :brand="$provider['button_brand']" :href="route('social-connections.redirect', $key)"
                            label="Reconnect {{ $label }}" />
                    @endif
                    @if ($provider['available'])
                        <form method="POST" action="{{ route('social-connections.verify', $key) }}" data-test-connection>
                            @csrf
                            <x-ui.button variant="secondary" icon="arrow-path">Test connection</x-ui.button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('social-connections.destroy', $key) }}"
                        onsubmit="return confirm('Disconnect {{ $label }}? Publishing and exporting to {{ $label }} will stop until you connect it again.')">
                        @csrf @method('DELETE')
                        <x-ui.button variant="danger" icon="x-mark">Disconnect</x-ui.button>
                    </form>
                @endif
            </x-ui.connection-card>
        @endforeach
    </div>

    @php
        $productsUrl = auth()->user()->isSeat() ? route('dashboard') : route('offers.index');
        $readyToPublish = $connections->contains(fn ($c) => ($providers[$c->provider]['publishes'] ?? false) && $c->verification_status === 'verified');
    @endphp
    <div class="mt-6">
        @if ($readyToPublish)
            <x-ui.next-step :href="$productsUrl" action="Open my products">
                Your account is verified. Open a product, render a UGC video, and use its Publish button to send it straight to your channel.
            </x-ui.next-step>
        @elseif ($connections->isNotEmpty())
            <x-ui.next-step :href="$productsUrl" action="Go to my products">
                Connected! Generate content for one of your products — the Publish and Export buttons there use these accounts.
            </x-ui.next-step>
        @else
            <x-ui.next-step>
                Start with the platform you post on most. Connecting takes under a minute and you can disconnect any time.
            </x-ui.next-step>
        @endif
    </div>

    {{-- The other places AffilStack connects to, so this page is the one stop for "what's linked". --}}
    <h3 class="mt-8 mb-3 font-display font-semibold text-base text-navy-900">Other connections</h3>
    <div class="grid gap-3 sm:grid-cols-3">
        @if (Route::has('email-connections.index') && ! auth()->user()->isSeat())
            <a href="{{ route('email-connections.index') }}" class="group flex items-center gap-3 rounded-2xl border border-line bg-surface p-4 transition hover:border-brand-500/40 hover:shadow-sm">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-surface-muted ring-1 ring-line"><x-ui.brand-icon name="gmail" class="h-5 w-5" /></span>
                <span class="min-w-0"><span class="block text-sm font-medium text-navy-900">Email sending</span><span class="block text-xs text-ink-600">Gmail or SMTP for CRM emails</span></span>
                <x-heroicon-m-chevron-right class="ml-auto h-4 w-4 text-ink-400 group-hover:text-brand-600" />
            </a>
        @endif
        <a href="{{ route('api-access.index') }}" class="group flex items-center gap-3 rounded-2xl border border-line bg-surface p-4 transition hover:border-brand-500/40 hover:shadow-sm">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-surface-muted ring-1 ring-line"><x-ui.brand-icon name="zapier" class="h-5 w-5" /></span>
            <span class="min-w-0"><span class="block text-sm font-medium text-navy-900">API & automations</span><span class="block text-xs text-ink-600">Zapier, Make, n8n, Sheets</span></span>
            <x-heroicon-m-chevron-right class="ml-auto h-4 w-4 text-ink-400 group-hover:text-brand-600" />
        </a>
        @if (Route::has('extension.index') && ! auth()->user()->isSeat())
            <a href="{{ route('extension.index') }}" class="group flex items-center gap-3 rounded-2xl border border-line bg-surface p-4 transition hover:border-brand-500/40 hover:shadow-sm">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-surface-muted ring-1 ring-line"><x-ui.brand-icon name="googlechrome" class="h-5 w-5" /></span>
                <span class="min-w-0"><span class="block text-sm font-medium text-navy-900">Chrome extension</span><span class="block text-xs text-ink-600">Capture leads while you browse</span></span>
                <x-heroicon-m-chevron-right class="ml-auto h-4 w-4 text-ink-400 group-hover:text-brand-600" />
            </a>
        @endif
    </div>

    @push('scripts')
        <script>
            // Show the "Checking…" state while a live test runs (the request is synchronous).
            document.querySelectorAll('form[data-test-connection]').forEach(function (form) {
                form.addEventListener('submit', function () {
                    const button = form.querySelector('button');
                    button.disabled = true;
                    button.textContent = 'Checking…';
                });
            });
        </script>
    @endpush
@endsection
