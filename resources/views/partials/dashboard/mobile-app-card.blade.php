{{-- "Get the mobile app" card for the customer dashboard sidebar — renders
     nothing until a store link is saved (or "Coming soon" is on) in
     Content → Mobile Apps. Included from layouts/app.blade.php. --}}
@if (\App\Support\MobileApps::isVisible())
    <div class="rounded-xl bg-gradient-to-br from-white/10 to-white/5 px-3 py-3 ring-1 ring-white/10">
        <div class="flex items-center gap-2 text-xs font-semibold text-white">
            <x-heroicon-o-device-phone-mobile class="h-4 w-4 text-gold-300" aria-hidden="true" />
            Get the mobile app
        </div>
        <p class="mt-1 text-[11px] leading-snug text-white/60">Your research, buyers and earnings on the go.</p>
        <div class="mt-2.5 flex flex-col gap-1.5">
            @if (\App\Support\MobileApps::playStoreUrl() || \App\Support\MobileApps::showsComingSoon())
                <x-ui.app-store-badge store="google" :url="\App\Support\MobileApps::playStoreUrl()" size="sm" class="w-full justify-start" />
            @endif
            @if (\App\Support\MobileApps::appStoreUrl() || \App\Support\MobileApps::showsComingSoon())
                <x-ui.app-store-badge store="apple" :url="\App\Support\MobileApps::appStoreUrl()" size="sm" class="w-full justify-start" />
            @endif
        </div>
    </div>
@endif
