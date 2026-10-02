{{-- The hero's designed product preview, used until an admin uploads a hero
     image or video. Illustrative sample data only — labelled as such. --}}
<figure class="relative mx-auto max-w-xl lg:max-w-none" aria-label="Illustration of the AffilStack dashboard with sample data">
    <div class="absolute -inset-6 rounded-[2.5rem] bg-gradient-to-tr from-brand-500/35 via-violet-500/15 to-gold-400/35 blur-3xl" aria-hidden="true"></div>

    <div class="relative rounded-3xl bg-white/[0.07] p-3 shadow-2xl ring-1 ring-white/15 backdrop-blur-xl sm:p-4" aria-hidden="true">
        <div class="flex items-center gap-2 px-2 pb-3">
            <span class="h-2.5 w-2.5 rounded-full bg-rose-400/80"></span>
            <span class="h-2.5 w-2.5 rounded-full bg-amber-300/80"></span>
            <span class="h-2.5 w-2.5 rounded-full bg-emerald-400/80"></span>
            <span class="ml-3 hidden rounded-full bg-white/10 px-3 py-1 font-mono text-[10px] text-white/50 sm:inline">app.affilstack.com/dashboard</span>
            <span class="ml-auto rounded-full bg-white/10 px-2 py-0.5 font-mono text-[9px] uppercase tracking-wider text-white/45">Sample data</span>
        </div>

        <div class="grid grid-cols-6 gap-3">
            {{-- Research --}}
            <div class="col-span-6 rounded-2xl bg-white p-4 text-navy-900 sm:col-span-4">
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-2 text-xs font-semibold"><span class="flex h-6 w-6 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-heroicon-m-magnifying-glass class="h-3.5 w-3.5" /></span> Product research</p>
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Go</span>
                </div>
                <p class="mt-3 font-display text-sm font-semibold">Online course: Email marketing for small business</p>
                <div class="mt-3 flex items-end justify-between">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider text-ink-400">Demand score</p>
                        <p class="font-display text-2xl font-semibold">92<span class="text-sm text-ink-400">/100</span></p>
                    </div>
                    <div class="flex flex-wrap justify-end gap-1">
                        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] text-brand-700">High intent</span>
                        <span class="rounded-full bg-violet-50 px-2 py-0.5 text-[10px] text-violet-700">40% commission</span>
                    </div>
                </div>
                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-surface-muted"><div class="h-full w-[92%] rounded-full bg-gradient-to-r from-brand-500 to-sky-400"></div></div>
            </div>

            {{-- Buyers --}}
            <div class="col-span-3 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 p-4 text-white sm:col-span-2">
                <p class="flex items-center gap-1.5 text-xs font-semibold"><x-heroicon-m-user-group class="h-4 w-4" /> Buyers found</p>
                <p class="mt-3 font-display text-3xl font-semibold">128</p>
                <p class="text-[11px] text-white/80">+24 added today</p>
                <div class="mt-3 flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-white"><x-ui.brand-icon name="googlemaps" class="h-4 w-4" /></span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-white"><x-ui.brand-icon name="linkedin" class="h-4 w-4" /></span>
                </div>
            </div>

            {{-- Campaign --}}
            <div class="col-span-3 rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-700 p-4 text-white sm:col-span-2">
                <p class="flex items-center gap-1.5 text-xs font-semibold"><x-heroicon-m-sparkles class="h-4 w-4 text-gold-300" /> AI campaign</p>
                <p class="mt-3 text-[11px] text-white/75">5-email sequence</p>
                <p class="font-display text-lg font-semibold leading-tight">Scheduled</p>
                <div class="mt-3 flex gap-1.5">
                    @foreach (['youtube', 'tiktok', 'x', 'pinterest'] as $channel)
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-white"><x-ui.brand-icon :name="$channel" class="h-3.5 w-3.5" /></span>
                    @endforeach
                </div>
            </div>

            {{-- Earnings --}}
            <div class="col-span-6 rounded-2xl bg-navy-900 p-4 text-white ring-1 ring-white/10 sm:col-span-4">
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-1.5 text-xs font-semibold"><x-heroicon-m-banknotes class="h-4 w-4 text-gold-300" /> Earnings this month</p>
                    <span class="rounded-full bg-emerald-400/15 px-2 py-0.5 text-[10px] font-semibold text-emerald-300">+38%</span>
                </div>
                <div class="mt-2 flex items-end justify-between gap-4">
                    <p class="font-display text-3xl font-semibold text-gold-300">$2,480</p>
                    <svg viewBox="0 0 160 48" class="h-12 w-40" fill="none">
                        <defs><linearGradient id="hero-earnings" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#d3a854" stop-opacity=".45"/><stop offset="1" stop-color="#d3a854" stop-opacity="0"/></linearGradient></defs>
                        <path d="M0 40 L20 36 L40 38 L60 28 L80 30 L100 18 L120 22 L140 10 L160 6 L160 48 L0 48 Z" fill="url(#hero-earnings)"/>
                        <path d="M0 40 L20 36 L40 38 L60 28 L80 30 L100 18 L120 22 L140 10 L160 6" stroke="#e0b563" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Floating notifications --}}
    <div class="home-float absolute -top-4 right-2 hidden items-center gap-2.5 rounded-2xl bg-white px-3.5 py-2.5 text-navy-900 shadow-xl ring-1 ring-black/5 sm:flex" aria-hidden="true">
        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gold-400 text-navy-950"><x-heroicon-s-currency-dollar class="h-5 w-5" /></span>
        <span class="leading-tight"><span class="block text-[10px] text-ink-400">New commission</span><span class="block text-sm font-semibold">+$48.00</span></span>
    </div>
    <div class="home-float-delayed absolute -bottom-10 left-6 hidden items-center gap-2.5 rounded-2xl bg-white px-3.5 py-2.5 text-navy-900 shadow-xl ring-1 ring-black/5 sm:flex" aria-hidden="true">
        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-100 text-violet-600"><x-heroicon-s-pencil-square class="h-4 w-4" /></span>
        <span class="leading-tight"><span class="block text-[10px] text-ink-400">AI content ready</span><span class="block text-sm font-semibold">12 posts · 3 videos</span></span>
    </div>
</figure>
