@use('App\Support\MobileApps')
{{-- Mobile apps band — hidden until a store link is saved (or "Coming soon"
     is switched on) in Content → Mobile Apps. --}}
@if (MobileApps::isVisible())
    <section id="get-the-app" class="relative isolate overflow-hidden bg-gradient-to-br from-brand-700 via-navy-900 to-navy-950 py-20 text-white sm:py-24" aria-labelledby="apps-heading">
        <div class="home-grid-pattern absolute inset-0 -z-10 opacity-60" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -right-24 top-10 -z-10 h-80 w-80 rounded-full bg-gold-400/25 blur-3xl" aria-hidden="true"></div>
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div data-reveal>
                <p class="inline-flex items-center gap-2 font-mono text-[11px] uppercase tracking-[0.2em] text-gold-300">
                    <x-heroicon-m-device-phone-mobile class="h-4 w-4" aria-hidden="true" />
                    {{ MobileApps::hasAnyLink() ? 'Mobile apps' : 'Mobile apps — coming soon' }}
                </p>
                <h2 id="apps-heading" class="mt-3 font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{{ MobileApps::appName() }}</h2>
                <p class="mt-4 max-w-lg text-base text-white/75 sm:text-lg">{{ MobileApps::pitch() }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    @if (MobileApps::playStoreUrl() || MobileApps::showsComingSoon())
                        <x-ui.app-store-badge store="google" :url="MobileApps::playStoreUrl()" />
                    @endif
                    @if (MobileApps::appStoreUrl() || MobileApps::showsComingSoon())
                        <x-ui.app-store-badge store="apple" :url="MobileApps::appStoreUrl()" />
                    @endif
                </div>
                @if (MobileApps::hasAnyLink())
                    <p class="mt-5 text-sm text-white/55">On your phone? <a href="{{ route('mobile-app.redirect') }}" class="font-semibold text-gold-300 underline-offset-4 hover:underline">Open the right store for your device</a>.</p>
                @endif
            </div>

            <div class="relative mx-auto w-64 sm:w-72" data-reveal>
                <div class="absolute -inset-10 rounded-full bg-brand-500/30 blur-3xl" aria-hidden="true"></div>
                <div class="relative rounded-[2.75rem] bg-navy-950 p-2.5 shadow-2xl ring-1 ring-white/20">
                    <div class="absolute left-1/2 top-4 z-10 h-5 w-24 -translate-x-1/2 rounded-full bg-black" aria-hidden="true"></div>
                    @if ($screenshot = MobileApps::screenshotUrl())
                        <img src="{{ $screenshot }}" alt="{{ MobileApps::screenshotAlt() }}" class="aspect-[9/19] w-full rounded-[2.2rem] object-cover" loading="lazy">
                    @else
                        <div class="aspect-[9/19] w-full overflow-hidden rounded-[2.2rem] bg-gradient-to-b from-surface-muted to-white px-4 pt-12 text-navy-900" aria-hidden="true">
                            <p class="text-[10px] text-ink-400">Good morning</p>
                            <p class="font-display text-base font-semibold">Your earnings</p>
                            <div class="mt-3 rounded-2xl bg-navy-900 p-3.5 text-white">
                                <p class="text-[10px] text-white/60">This month</p>
                                <p class="font-display text-2xl font-semibold text-gold-300">$2,480</p>
                                <div class="mt-2 flex h-10 items-end gap-1">
                                    @foreach ([30, 45, 38, 60, 52, 75, 68, 90] as $h)
                                        <span class="flex-1 rounded-sm bg-gradient-to-t from-gold-500 to-gold-300" style="height: {{ $h }}%"></span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="mt-3 space-y-2">
                                @foreach ([['map-pin', 'bg-emerald-50 text-emerald-600', '12 new buyers', 'Google Maps'], ['envelope', 'bg-violet-50 text-violet-600', 'Campaign sent', '5 replies'], ['link', 'bg-brand-50 text-brand-600', '348 clicks', 'Today']] as [$icon, $tone, $title, $meta])
                                    <div class="flex items-center gap-2.5 rounded-xl bg-white p-2.5 ring-1 ring-line">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-lg {{ $tone }}">@include('marketing.home.partials.icon', ['icon' => $icon, 'class' => 'h-4 w-4'])</span>
                                        <span class="leading-tight"><span class="block text-[11px] font-semibold">{{ $title }}</span><span class="block text-[9px] text-ink-400">{{ $meta }}</span></span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif
