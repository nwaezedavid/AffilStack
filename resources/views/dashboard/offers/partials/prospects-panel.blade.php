{{-- "Your prospects for this product": the CRM list the Chrome extension
     fills from Google Maps / LinkedIn, and the one-click campaign to it.
     Owner-only (CRM is account-wide), so $prospectList is null for seats. --}}
@if ($prospectList)
    @php
        $campaignUrl = \Illuminate\Support\Facades\Route::has('crm.campaigns.create') ? route('crm.campaigns.create', ['list' => $prospectList->id]) : null;
        $listUrl = \Illuminate\Support\Facades\Route::has('crm.lists.show') ? route('crm.lists.show', $prospectList) : route('crm.index');
    @endphp
    <section data-tour="prospects" class="@container relative overflow-hidden rounded-3xl bg-gradient-to-br from-navy-950 via-navy-900 to-emerald-900 p-5 text-white shadow-lg sm:p-7">
        <div class="pointer-events-none absolute -right-10 -top-10 h-48 w-48 rounded-full bg-emerald-400/20 blur-3xl" aria-hidden="true"></div>
        <div class="relative grid gap-6 @4xl:grid-cols-[1fr_auto] @4xl:items-center">
            <div>
                <p class="text-[11px] font-mono uppercase tracking-[0.18em] text-emerald-300">Step 2 · Collect buyers</p>
                <h3 class="mt-1 font-display text-xl font-semibold">Your prospects for this product</h3>
                <p class="mt-1 text-sm text-white/75">Every business or person you save with the Chrome extension lands in <strong class="text-white">{{ $prospectList->name }}</strong> — then one click emails them all.</p>

                <ol class="mt-5 grid gap-3 @xl:grid-cols-3">
                    @foreach ([
                        ['puzzle-piece', 'Install the Chrome extension', route('extension.index')],
                        ['map', 'Search a keyword on Google Maps or LinkedIn', null],
                        ['plus-circle', 'Click "Add to CRM" on each result', null],
                    ] as $i => [$icon, $text, $href])
                        <li class="flex items-start gap-3 rounded-2xl bg-white/10 p-3 ring-1 ring-white/15">
                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-400 text-xs font-bold text-navy-950">{{ $i + 1 }}</span>
                            <span class="text-sm leading-snug">
                                @if ($href)
                                    <a href="{{ $href }}" class="underline decoration-emerald-300/60 underline-offset-2 hover:text-emerald-200">{{ $text }}</a>
                                @else
                                    {{ $text }}
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="flex flex-row items-center gap-4 rounded-2xl bg-white/10 p-4 ring-1 ring-white/15 @4xl:flex-col @4xl:items-stretch @4xl:p-5 @4xl:text-center">
                <div class="@4xl:mb-1">
                    <p class="font-display text-4xl font-semibold tabular-nums">{{ number_format($prospectCount) }}</p>
                    <p class="text-xs text-white/70">{{ \Illuminate\Support\Str::plural('prospect', $prospectCount) }} saved</p>
                </div>
                <div class="flex flex-1 flex-col gap-2">
                    @if ($campaignUrl)
                        <a href="{{ $campaignUrl }}" data-tour="create-campaign"
                           class="inline-flex items-center justify-center gap-1.5 rounded-full bg-gold-400 px-5 py-2.5 text-sm font-semibold text-navy-950 shadow hover:bg-gold-300 transition">
                            <x-heroicon-m-paper-airplane class="h-4 w-4" /> Create Campaign
                        </a>
                    @endif
                    <a href="{{ $listUrl }}" class="inline-flex items-center justify-center gap-1.5 rounded-full bg-white/15 px-5 py-2 text-sm font-medium text-white ring-1 ring-white/25 hover:bg-white/25 transition">
                        <x-heroicon-m-users class="h-4 w-4" /> View prospects
                    </a>
                </div>
            </div>
        </div>
        @if ($prospectCount === 0)
            <p class="relative mt-5 flex items-center gap-2 text-xs text-white/70">
                <x-heroicon-m-light-bulb class="h-4 w-4 text-gold-300" />
                No prospects yet — copy a Google Maps keyword above, open Maps, and add your first 10 businesses.
            </p>
        @endif
    </section>
@endif
