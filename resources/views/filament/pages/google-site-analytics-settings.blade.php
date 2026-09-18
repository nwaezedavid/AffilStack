<x-filament-panels::page>
    @include('filament.pages._panel-styles')

    @php($settings = $this->settings())

    <div class="afs-panel-hero">
        <div class="afs-panel-hero-icon">
            <x-filament::icon icon="heroicon-o-chart-bar-square" />
        </div>
        <div>
            <p>
                One Google connection auto-creates a GA4 property, verifies Search Console ownership, and creates a
                Tag Manager container for this site — the same idea as Google Site Kit for WordPress. Reuses the
                OAuth client id/secret from Site &gt; Google Login.
            </p>
        </div>
    </div>

    <div class="afs-panel-card" style="margin-bottom: 1rem;">
        <div class="afs-panel-card-head">
            <div class="afs-panel-card-title">
                <x-filament::icon icon="heroicon-o-link" />
                Google connection
            </div>
            @if ($settings->isConnected())
                <span class="afs-panel-badge afs-panel-badge--success">Connected as {{ $settings->connected_email }}</span>
            @else
                <span class="afs-panel-badge afs-panel-badge--neutral">Not connected</span>
            @endif
        </div>

        <p style="margin-bottom: 0.75rem;">
            Approving access grants AffilStack permission to manage Analytics, Search Console, and Tag Manager on
            your behalf — nothing else.
        </p>

        <div class="afs-panel-actions">
            <x-filament::button tag="a" href="{{ $this->connectUrl() }}" icon="heroicon-o-arrow-top-right-on-square">
                {{ $settings->isConnected() ? 'Reconnect with Google' : 'Connect with Google' }}
            </x-filament::button>

            @if ($settings->isConnected())
                <x-filament::button color="danger" outlined wire:click="disconnect" wire:confirm="Disconnect Google Site Analytics? Provisioned property/container ids stay recorded, but this connection can no longer refresh them.">
                    Disconnect
                </x-filament::button>
            @endif
        </div>
    </div>

    <div class="afs-panel-grid">
        {{-- Google Analytics 4 --}}
        <div class="afs-panel-card">
            <div class="afs-panel-card-head">
                <div class="afs-panel-card-title">
                    <x-filament::icon icon="heroicon-o-presentation-chart-line" />
                    Google Analytics 4
                </div>
                @if ($settings->hasGa4())
                    <span class="afs-panel-badge afs-panel-badge--success">{{ $settings->credential('ga_measurement_id') }}</span>
                @else
                    <span class="afs-panel-badge afs-panel-badge--neutral">Not set up</span>
                @endif
            </div>

            @if (! $settings->isConnected())
                <p>Connect with Google above first.</p>
            @elseif ($settings->hasGa4())
                <p>Tracking is live sitewide — the gtag/GTM snippet is injected automatically whenever this measurement ID is set.</p>
            @else
                <p>Pick the Analytics account to create a property under, or create your first Analytics account at analytics.google.com if the list comes back empty.</p>
                <div class="afs-panel-actions" style="margin-top: 0.75rem;">
                    <x-filament::button color="gray" wire:click="loadAnalyticsAccounts">Load Analytics accounts</x-filament::button>
                </div>
                @if ($analyticsAccounts)
                    <ul class="afs-panel-list">
                        @foreach ($analyticsAccounts as $account)
                            <li class="afs-panel-row">
                                <span>{{ $account['displayName'] }}</span>
                                <x-filament::button size="sm" wire:click="setUpAnalytics('{{ $account['name'] }}')">Create GA4 property</x-filament::button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>

        {{-- Search Console --}}
        <div class="afs-panel-card">
            <div class="afs-panel-card-head">
                <div class="afs-panel-card-title">
                    <x-filament::icon icon="heroicon-o-magnifying-glass-circle" />
                    Search Console
                </div>
                @if ($settings->hasSearchConsole())
                    <span class="afs-panel-badge afs-panel-badge--success">Verified</span>
                @else
                    <span class="afs-panel-badge afs-panel-badge--neutral">Not verified</span>
                @endif
            </div>

            @if (! $settings->isConnected())
                <p>Connect with Google above first.</p>
            @elseif ($settings->hasSearchConsole())
                <p>{{ $settings->credential('gsc_site_url') }} is verified and its sitemap has been submitted.</p>
                <div class="afs-panel-actions" style="margin-top: 0.75rem;">
                    <x-filament::button size="sm" color="gray" wire:click="verifySearchConsole">Re-submit sitemap</x-filament::button>
                </div>
            @else
                <p>
                    Verification only succeeds once this site is publicly reachable — Google fetches the live page to
                    confirm the tag. If this isn't deployed yet, come back and click this after it is.
                </p>
                <div class="afs-panel-actions" style="margin-top: 0.75rem;">
                    <x-filament::button wire:click="verifySearchConsole">Verify Search Console</x-filament::button>
                </div>
            @endif
        </div>

        {{-- Tag Manager --}}
        <div class="afs-panel-card">
            <div class="afs-panel-card-head">
                <div class="afs-panel-card-title">
                    <x-filament::icon icon="heroicon-o-tag" />
                    Tag Manager
                </div>
                @if ($settings->hasTagManager())
                    <span class="afs-panel-badge afs-panel-badge--success">{{ $settings->credential('gtm_public_id') }}</span>
                @else
                    <span class="afs-panel-badge afs-panel-badge--neutral">Not set up</span>
                @endif
            </div>

            @if (! $settings->isConnected())
                <p>Connect with Google above first.</p>
            @elseif ($settings->hasTagManager())
                <p>The container snippet is injected sitewide automatically — manage tags, triggers, and variables from tagmanager.google.com as usual.</p>
            @else
                <p>Pick the Tag Manager account to create a container under, or create your first account at tagmanager.google.com if the list comes back empty.</p>
                <div class="afs-panel-actions" style="margin-top: 0.75rem;">
                    <x-filament::button color="gray" wire:click="loadTagManagerAccounts">Load Tag Manager accounts</x-filament::button>
                </div>
                @if ($tagManagerAccounts)
                    <ul class="afs-panel-list">
                        @foreach ($tagManagerAccounts as $account)
                            <li class="afs-panel-row">
                                <span>{{ $account['name'] }}</span>
                                <x-filament::button size="sm" wire:click="setUpTagManager('{{ $account['accountId'] }}')">Create container</x-filament::button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    </div>
</x-filament-panels::page>
