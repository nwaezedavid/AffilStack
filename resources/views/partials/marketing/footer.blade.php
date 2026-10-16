<?php
    /*
     * Public site footer: brand + socials, link columns (Website → Navigation
     * menu), store badges once
     * a mobile app link exists (Content → Mobile Apps) and the admin's
     * footer text (Site > Brand Settings — also previewable by Tony).
     */
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
    $footerText = \App\Models\SiteSetting::get('footer_text', '© '.date('Y').' '.$siteName.'.');
    // Columns, links and the two lines of copy are edited in Website →
    // Navigation menu (App\Support\Website\NavigationMenu).
    $columns = \App\Support\Website\NavigationMenu::footer();
?>
<footer class="relative overflow-hidden bg-navy-950 text-white/70" aria-labelledby="site-footer-heading">
    <h2 id="site-footer-heading" class="sr-only">Site footer</h2>
    <div class="pointer-events-none absolute -top-40 left-1/2 h-80 w-[48rem] -translate-x-1/2 rounded-full bg-brand-600/20 blur-3xl" aria-hidden="true"></div>
    <div class="relative mx-auto max-w-7xl px-4 pt-16 pb-10 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5 font-display text-lg font-semibold text-white">
                    @if ($whiteLogo = \App\Support\Brand::whiteLogoUrl())
                        <img src="{{ $whiteLogo }}" alt="{{ $siteName }}" class="h-9 w-auto" loading="lazy">
                    @else
                        @if ($whiteIcon = \App\Support\Brand::whiteIconUrl())
                            <img src="{{ $whiteIcon }}" alt="" class="h-9 w-9 object-contain" loading="lazy" aria-hidden="true">
                        @else
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-navy-800 text-sm text-gold-300 ring-1 ring-white/15" aria-hidden="true">{{ strtoupper(substr($siteName, 0, 2)) }}</span>
                        @endif
                        {{ $siteName }}
                    @endif
                </a>
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-white/60">{{ \App\Support\Website\NavigationMenu::label('footer_tagline') }}</p>
                <x-social-links variant="inverted" class="mt-6" />

                @if (\App\Support\MobileApps::isVisible())
                    <div class="mt-6 flex flex-wrap gap-2">
                        @if (\App\Support\MobileApps::playStoreUrl() || \App\Support\MobileApps::showsComingSoon())
                            <x-ui.app-store-badge store="google" :url="\App\Support\MobileApps::playStoreUrl()" size="sm" />
                        @endif
                        @if (\App\Support\MobileApps::appStoreUrl() || \App\Support\MobileApps::showsComingSoon())
                            <x-ui.app-store-badge store="apple" :url="\App\Support\MobileApps::appStoreUrl()" size="sm" />
                        @endif
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-8 sm:grid-cols-4 lg:col-span-8">
                @foreach ($columns as $column)
                    <div>
                        <h3 class="font-mono text-[11px] uppercase tracking-[0.18em] text-gold-300">{{ $column['heading'] }}</h3>
                        <ul class="mt-4 space-y-3 text-sm">
                            @foreach ($column['links'] as $item)
                                <li>
                                    <a href="{{ $item['url'] }}" @if (! empty($item['external'])) rel="noopener" @endif class="text-white/70 transition hover:text-white focus:outline-none focus-visible:underline">{{ $item['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-12 flex flex-col gap-3 border-t border-white/10 pt-6 text-xs text-white/50 sm:flex-row sm:items-center sm:justify-between">
            <p>{{ $footerText }}</p>
            @include('partials.locale.website-switcher', ['variant' => 'footer'])
            <p>{{ \App\Support\Website\NavigationMenu::label('footer_bottom_note') }}</p>
        </div>
    </div>
</footer>
