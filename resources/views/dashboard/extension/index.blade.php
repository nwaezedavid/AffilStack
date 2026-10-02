@extends('layouts.app')

@section('title', 'Browser Extension')

@php
    $storeUrl = config('extension.store_url');
    $apiBase = \App\Http\Controllers\Dashboard\ExtensionController::apiBaseUrl();
    $isConnected = $lastUsedToken !== null;
    $campaignsReady = Route::has('crm.campaigns.create');
@endphp

@section('content')
    <x-ui.page-header
        eyebrow="Chrome extension · v{{ config('extension.version') }}"
        title="AffilStack for Chrome"
        subtitle="Add Google Maps businesses to your CRM in one click, write personalised LinkedIn messages, and let AI craft the reply that closes the sale."
        icon="puzzle-piece"
    >
        <x-slot:actions>
            @if ($storeUrl)
                <x-ui.button variant="gold" :href="$storeUrl" icon="plus" target="_blank" rel="noopener">Add to Chrome</x-ui.button>
                <x-ui.button variant="light" :href="route('extension.download')" icon="arrow-down-tray">Download .zip</x-ui.button>
            @else
                <x-ui.button variant="gold" :href="route('extension.download')" icon="arrow-down-tray">Download extension</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Where the extension fits in the core workflow --}}
    <section class="mb-6" aria-labelledby="workflow-heading">
        <div class="flex items-end justify-between gap-3 mb-3">
            <div>
                <h2 id="workflow-heading" class="font-display font-semibold text-lg text-navy-900">How it fits your workflow</h2>
                <p class="text-sm text-ink-600">Your product research gives you the keywords — the extension turns what you find into customers.</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="relative overflow-hidden rounded-2xl border border-line bg-surface p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
                <div class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-emerald-100/60 blur-2xl" aria-hidden="true"></div>
                <div class="relative flex items-center gap-3 mb-4">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-surface-muted ring-1 ring-line"><x-ui.brand-icon name="googlemaps" class="h-5 w-5" /></span>
                    <div>
                        <h3 class="font-display font-semibold text-navy-900">Google Maps → CRM → Campaign</h3>
                        <p class="text-xs text-ink-600">Find local businesses that need your product</p>
                    </div>
                </div>
                <ol class="relative space-y-3">
                    @foreach ([
                        ['Research a product', 'AffilStack suggests the best Google Maps keywords for it.', 'magnifying-glass'],
                        ['Search a Maps keyword', 'Open a business, or keep the results list open.', 'map'],
                        ['Click the AffilStack icon → Add to CRM', 'Pick the product — businesses land in its own list, duplicates skipped.', 'user-plus'],
                        ['Create a campaign for that list', 'Email them all from your own address, with autopilot for new additions.', 'paper-airplane'],
                    ] as $i => [$step, $detail, $stepIcon])
                        <li class="flex gap-3">
                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[11px] font-semibold text-white">{{ $i + 1 }}</span>
                            <div>
                                <p class="text-sm font-medium text-ink-900">{{ $step }}</p>
                                <p class="text-xs text-ink-600">{{ $detail }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="relative overflow-hidden rounded-2xl border border-line bg-surface p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
                <div class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-brand-100/70 blur-2xl" aria-hidden="true"></div>
                <div class="relative flex items-center gap-3 mb-4">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-surface-muted ring-1 ring-line"><x-ui.brand-icon name="linkedin" class="h-5 w-5" /></span>
                    <div>
                        <h3 class="font-display font-semibold text-navy-900">LinkedIn → message → AI replies</h3>
                        <p class="text-xs text-ink-600">Start conversations that turn into sales</p>
                    </div>
                </div>
                <ol class="relative space-y-3">
                    @foreach ([
                        ['Search a LinkedIn keyword', 'Use the people and company keywords from your research.', 'magnifying-glass'],
                        ['Open a profile → Generate message', 'A personalised connection note, first message and two follow-ups ('.config('credits.costs.extension_linkedin_message').' credits).', 'sparkles'],
                        ['Insert it and send it yourself', 'One click puts it in LinkedIn\'s message box — you stay in control.', 'chat-bubble-left-right'],
                        ['When they reply → Suggest reply', 'AI handles objections and moves toward the sale ('.config('credits.costs.extension_reply_suggestion').' credits).', 'bolt'],
                    ] as $i => [$step, $detail, $stepIcon])
                        <li class="flex gap-3">
                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[11px] font-semibold text-white">{{ $i + 1 }}</span>
                            <div>
                                <p class="text-sm font-medium text-ink-900">{{ $step }}</p>
                                <p class="text-xs text-ink-600">{{ $detail }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5 mb-6">
        {{-- Connection status --}}
        <x-ui.connection-card
            brand="googlechrome"
            name="Chrome extension"
            class="min-w-0 lg:col-span-2"
            :status="$isConnected ? 'verified' : 'not_connected'"
            :status-label="$isConnected ? 'Extension connected' : 'Not connected yet'"
            :account="$isConnected ? 'Last used '.$lastUsedToken->last_used_at->diffForHumans().' · '.$lastUsedToken->name : null"
            :description="$isConnected
                ? 'Your extension is talking to this account. Open Google Maps or LinkedIn and click the AffilStack icon.'
                : 'Install the extension, generate a token below and paste it into the extension\'s settings — this turns green the first time it connects.'"
        >
            @if (! $isConnected)
                <x-ui.button size="sm" href="#tokens" icon="key">Generate a token</x-ui.button>
            @else
                <x-ui.button size="sm" variant="secondary" href="https://www.google.com/maps" target="_blank" rel="noopener" icon="map">Open Google Maps</x-ui.button>
            @endif
        </x-ui.connection-card>

        <div class="min-w-0 lg:col-span-3 grid grid-cols-3 gap-3 sm:gap-4">
            <x-ui.stat label="Contacts added" :value="number_format($stats['contacts'])" icon="user-plus" tone="emerald" class="min-w-0" hint="from Maps & LinkedIn" />
            <x-ui.stat label="AI replies" :value="number_format($stats['replies'])" icon="chat-bubble-left-right" tone="brand" class="min-w-0" hint="drafted for prospects" />
            <x-ui.stat label="Pages saved" :value="number_format($stats['clips'])" icon="bookmark" tone="gold" class="min-w-0" hint="to your research" />
        </div>
    </div>

    {{-- Install --}}
    <x-ui.card title="1. Install the extension" :description="$storeUrl ? 'One click from the Chrome Web Store.' : 'Takes about a minute — Chrome calls this loading an unpacked extension.'" icon="arrow-down-tray" class="mb-6">
        @if ($storeUrl)
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <ol class="text-sm text-ink-600 space-y-1.5 list-decimal list-inside">
                    <li>Add AffilStack from the Chrome Web Store and pin its icon to your toolbar.</li>
                    <li>Click the icon → <strong class="text-ink-900">Add your token</strong>, and paste a token from step 2.</li>
                </ol>
                <div class="flex items-center gap-3 flex-wrap">
                    <x-ui.button variant="primary" :href="$storeUrl" target="_blank" rel="noopener" icon="plus">Add to Chrome</x-ui.button>
                    <a href="{{ route('extension.download') }}" class="text-sm text-brand-600 hover:text-brand-700">or download it to install manually (.zip)</a>
                </div>
            </div>
        @else
            <ol class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 mb-5">
                @foreach ([
                    'Download the extension and unzip it somewhere permanent.',
                    'In Chrome, open <span class="font-mono text-xs bg-surface-muted px-1.5 py-0.5 rounded">chrome://extensions</span> and turn on <strong>Developer mode</strong> (top right).',
                    'Click <strong>Load unpacked</strong> and select the unzipped <span class="font-mono text-xs bg-surface-muted px-1.5 py-0.5 rounded">affilstack-extension</span> folder.',
                    'Pin the AffilStack icon, click it → <strong>Add your token</strong>, and paste a token from step 2.',
                ] as $i => $step)
                    <li class="flex gap-3 rounded-xl bg-surface-muted/70 p-3 ring-1 ring-line">
                        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-navy-900 text-[11px] font-semibold text-gold-300">{{ $i + 1 }}</span>
                        <span class="text-sm text-ink-600">{!! $step !!}</span>
                    </li>
                @endforeach
            </ol>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-ink-600">The download already points at this site (<span class="font-mono bg-surface-muted px-1.5 py-0.5 rounded break-all">{{ $apiBase }}</span>) — you only paste a token. Updating from an older version? Replace the folder's files, then click reload on the extension's card.</p>
                <x-ui.button variant="primary" :href="route('extension.download')" icon="arrow-down-tray" class="shrink-0">Download extension (.zip)</x-ui.button>
            </div>
        @endif
    </x-ui.card>

    {{-- Tokens --}}
    <x-ui.card id="tokens" title="2. Connect it to your account" description="The extension signs in with a token, never your password. Give each device its own token so you can revoke one without affecting the others." icon="key" class="mb-6 scroll-mt-24">
        <form method="POST" action="{{ route('extension.tokens.store') }}" class="flex flex-col gap-2 sm:flex-row sm:items-end mb-5">
            @csrf
            <div class="flex-1 max-w-sm">
                <label for="token-name" class="block text-xs font-medium text-ink-600 mb-1">Device name</label>
                <input id="token-name" name="name" required placeholder="e.g. Work laptop" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <x-ui.button icon="plus">Generate token</x-ui.button>
        </form>

        @if ($tokens->isEmpty())
            <p class="rounded-xl border border-dashed border-line bg-surface-muted/50 px-4 py-3 text-sm text-ink-600">No tokens yet — generate one above and paste it into the extension's settings.</p>
        @else
            <div class="border border-line rounded-xl overflow-hidden">
                <div class="relative overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                            <tr>
                                <th class="text-left px-4 py-2">Device</th>
                                <th class="hidden sm:table-cell text-left px-4 py-2">Status</th>
                                <th class="text-left px-4 py-2">Last used</th>
                                <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($tokens as $token)
                                <tr>
                                    <td class="px-4 py-2.5 font-medium text-ink-900">{{ $token->name }}</td>
                                    <td class="hidden sm:table-cell px-4 py-2.5">
                                        <x-ui.status-pill :status="$token->last_used_at ? 'verified' : 'connected'" :label="$token->last_used_at ? 'In use' : 'Waiting for first use'" />
                                    </td>
                                    <td class="px-4 py-2.5 text-ink-600 whitespace-nowrap">{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <form method="POST" action="{{ route('extension.tokens.destroy', $token) }}" onsubmit="return confirm('Revoke this token? Any extension using it will stop working.')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs font-medium text-red-600 hover:text-red-700">Revoke</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-ui.card>

    {{-- What to search next --}}
    <x-ui.card title="3. Start prospecting" description="Keywords from your product research — open one, then click the AffilStack icon on what you find." icon="rocket-launch" class="mb-6">
        @if ($keywordOffers->isEmpty())
            <x-ui.empty-state icon="magnifying-glass" title="Research a product to get your keywords" :href="route('offers.create')" action="Research a product">
                AffilStack suggests the best Google Maps and LinkedIn keywords for each product you research — they'll appear here, one click away.
            </x-ui.empty-state>
        @else
            <div class="space-y-5">
                @foreach ($keywordOffers as $item)
                    @php
                        $maps = array_slice($item['research']->mapsKeywords(), 0, 4);
                        $location = $item['research']->locations()[0] ?? null;
                        $linkedin = $item['research']->linkedinKeywords();
                        $people = array_slice($linkedin['people'], 0, 3);
                        $companies = array_slice($linkedin['companies'], 0, 2);
                    @endphp
                    <div @class(['pt-5 border-t border-line' => ! $loop->first])>
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <p class="font-display font-semibold text-navy-900">{{ $item['offer']->product_name }}</p>
                            <div class="flex flex-wrap items-center gap-3 text-xs">
                                @if ($item['list'] && Route::has('crm.lists.show'))
                                    <a href="{{ route('crm.lists.show', $item['list']) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $item['list']->name }} &rarr;</a>
                                @endif
                                @if ($item['list'] && $campaignsReady)
                                    <a href="{{ route('crm.campaigns.create', ['list' => $item['list']->id]) }}" class="font-medium text-emerald-700 hover:text-emerald-800">Create campaign &rarr;</a>
                                @endif
                            </div>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            @if ($maps !== [])
                                <div class="rounded-xl bg-emerald-50/60 p-3 ring-1 ring-emerald-100">
                                    <p class="flex items-center gap-1.5 text-[11px] font-mono uppercase tracking-wide text-emerald-800 mb-2"><x-ui.brand-icon name="googlemaps" class="h-3.5 w-3.5" /> Google Maps</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($maps as $keyword)
                                            <a href="{{ \App\Support\ResearchPresenter::mapsUrl($keyword['keyword'], $location) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-xs font-medium text-ink-900 ring-1 ring-emerald-200 hover:ring-emerald-400 hover:bg-emerald-50 transition">
                                                {{ $keyword['keyword'] }}@if ($location)<span class="text-ink-400">· {{ $location }}</span>@endif
                                                <x-heroicon-m-arrow-top-right-on-square class="h-3 w-3 text-ink-400" />
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @if ($people !== [] || $companies !== [])
                                <div class="rounded-xl bg-brand-50/60 p-3 ring-1 ring-brand-100">
                                    <p class="flex items-center gap-1.5 text-[11px] font-mono uppercase tracking-wide text-brand-700 mb-2"><x-ui.brand-icon name="linkedin" class="h-3.5 w-3.5" /> LinkedIn</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($people as $keyword)
                                            <a href="{{ \App\Support\ResearchPresenter::linkedinUrl('people', $keyword) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-xs font-medium text-ink-900 ring-1 ring-brand-200 hover:ring-brand-400 hover:bg-brand-50 transition">
                                                <x-heroicon-m-user class="h-3 w-3 text-ink-400" /> {{ $keyword }}
                                            </a>
                                        @endforeach
                                        @foreach ($companies as $keyword)
                                            <a href="{{ \App\Support\ResearchPresenter::linkedinUrl('companies', $keyword) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-1 text-xs font-medium text-ink-900 ring-1 ring-brand-200 hover:ring-brand-400 hover:bg-brand-50 transition">
                                                <x-heroicon-m-building-office class="h-3 w-3 text-ink-400" /> {{ $keyword }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    @if ($isConnected && $campaignsReady)
        <x-ui.next-step :href="route('crm.campaigns.create')" action="Create a campaign" class="mb-6">
            Added businesses from Google Maps? Send them a campaign from your own email — autopilot follows up with every new contact you add.
        </x-ui.next-step>
    @elseif (! $isConnected)
        <x-ui.next-step href="#tokens" action="Generate a token" class="mb-6">
            Install the extension, then connect it with a token — it takes about a minute.
        </x-ui.next-step>
    @endif

    {{-- Captured clips --}}
    <x-ui.card title="Saved pages" description="Pages saved with the extension's Save page tab — attach one to a product, or turn it into a new one." icon="bookmark">
        @if ($clips->isEmpty())
            <p class="text-sm text-ink-500">Nothing captured yet — once the extension is connected, use its <strong>Save page</strong> tab on any page and it'll show up here.</p>
        @else
            <div class="divide-y divide-line-soft">
                @foreach ($clips as $clip)
                    <div class="py-3 first:pt-0">
                        <div class="flex items-center gap-2 flex-wrap text-xs text-ink-400 font-mono uppercase tracking-wide">
                            <span>{{ config('extension.page_types')[$clip->page_type] ?? $clip->page_type }}</span>
                            <span>&middot;</span>
                            <span>{{ $clip->created_at->format('M j, Y') }}</span>
                            @if ($clip->offer)
                                <span>&middot;</span>
                                <span class="normal-case">attached to {{ $clip->offer->product_name }}</span>
                            @endif
                        </div>
                        <div class="text-sm font-medium text-ink-900 mt-1">{{ $clip->title ?: $clip->source_url }}</div>
                        <a href="{{ $clip->source_url }}" target="_blank" rel="noopener" class="text-xs text-brand-600 hover:text-brand-700 break-all">{{ $clip->source_url }}</a>
                        @if ($clip->selected_text)
                            <p class="text-sm text-ink-600 mt-2 line-clamp-2">{{ $clip->selected_text }}</p>
                        @endif

                        <div class="flex items-center gap-3 flex-wrap mt-2.5">
                            <form method="POST" action="{{ route('extension.clips.attach', $clip) }}" class="flex items-center gap-2">
                                @csrf @method('PATCH')
                                <label for="clip-offer-{{ $clip->id }}" class="sr-only">Attach to product</label>
                                <select id="clip-offer-{{ $clip->id }}" name="offer_id" onchange="this.form.submit()" class="rounded-md border border-line px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500">
                                    <option value="">Not attached</option>
                                    @foreach ($offers as $offer)
                                        <option value="{{ $offer->id }}" @selected($clip->offer_id === $offer->id)>{{ $offer->product_name }}</option>
                                    @endforeach
                                </select>
                            </form>
                            @unless ($clip->offer_id)
                                <a href="{{ route('offers.create', ['product_name' => $clip->title, 'product_url' => $clip->source_url, 'clip_id' => $clip->id]) }}" class="text-xs text-brand-600 hover:text-brand-700">
                                    Create offer from this &rarr;
                                </a>
                            @endunless
                            <form method="POST" action="{{ route('extension.clips.destroy', $clip) }}" onsubmit="return confirm('Remove this captured page?')" class="ml-auto">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:text-red-700">Remove</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    <p class="mt-6 text-xs text-ink-400 max-w-3xl">
        Privacy: the extension reads a page only when you click its icon, and only that tab — business details on Google Maps, profile details on LinkedIn. Nothing is sent to AffilStack until you click Add, Generate, Suggest or Save, and it never sends messages on LinkedIn for you.
    </p>
@endsection
