@extends('layouts.app')

@section('title', 'WordPress Sites')

@php
    use App\Services\WordPress\WordPressSiteService;
    $included = $limit !== 0;
    $atLimit = $limit > 0 && $used >= $limit;
    $seoNames = ['yoast' => 'Yoast SEO', 'rank_math' => 'Rank Math', 'aioseo' => 'All in One SEO'];
@endphp

@section('content')
    <x-ui.page-header
        eyebrow="Connections"
        title="WordPress Sites"
        subtitle="Send your AffilStack blog articles to your own WordPress sites as drafts, with images, SEO fields, categories, tags, FAQ schema and your tracked links already in place. You review and publish."
        icon="globe-alt"
        tone="brand"
    >
        <x-slot:actions>
            @if ($included)
                <x-ui.button variant="gold" :href="route('wordpress.download')" icon="arrow-down-tray">Download the plugin</x-ui.button>
            @else
                <x-ui.button variant="gold" :href="route('billing.index')" icon="sparkles">Upgrade to use it</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @unless ($included)
        <div class="mb-6 rounded-2xl border border-gold-300/60 bg-gold-300/15 p-5 text-sm text-navy-900">
            <p class="font-semibold">Your plan doesn't include the WordPress plugin.</p>
            <p class="mt-1 text-ink-600">Upgrade to send articles to your WordPress site as ready-to-edit drafts with one click, or automatically.</p>
        </div>
    @endunless

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-base font-semibold text-navy-900">Your sites</h2>
                <span class="text-xs text-ink-400">{{ $used }} connected · {{ WordPressSiteService::limitLabel($limit) }} on your plan</span>
            </div>

            @forelse ($sites as $site)
                <x-ui.card padding="p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-display font-semibold text-navy-900">{{ $site->displayName() }}</p>
                                @if ($site->isConnected())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Connected</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Waiting for the plugin</span>
                                @endif
                            </div>
                            <a href="{{ $site->site_url }}" target="_blank" rel="noopener" class="mt-0.5 block truncate text-sm text-brand-600 hover:text-brand-700">{{ $site->site_url }}</a>
                            <p class="mt-2 text-xs text-ink-400">
                                {{ $site->sent_count }} {{ \Illuminate\Support\Str::plural('article', $site->sent_count) }} sent
                                @if ($site->seo_plugin) · SEO: {{ $seoNames[$site->seo_plugin] ?? $site->seo_plugin }} @endif
                                @if ($site->plugin_version) · Plugin {{ $site->plugin_version }}@if (version_compare($site->plugin_version, $version, '<')) <span class="font-medium text-amber-700">(update available)</span>@endif @endif
                                @if ($site->last_seen_at) · Last contact {{ $site->last_seen_at->diffForHumans() }} @endif
                            </p>
                            @if ($site->last_error)
                                <p class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ $site->last_error }}</p>
                            @endif
                        </div>
                        @if ($isOwner)
                            <div class="flex shrink-0 flex-wrap gap-2">
                                @if ($site->isConnected())
                                    <form method="POST" action="{{ route('wordpress.sites.test', $site) }}">
                                        @csrf
                                        <x-ui.button size="sm" variant="secondary" icon="signal">Test</x-ui.button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('wordpress.sites.destroy', $site) }}" onsubmit="return confirm('Disconnect {{ e($site->displayName()) }}? Drafts already on the site stay there.')">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button size="sm" variant="danger" icon="x-mark">Disconnect</x-ui.button>
                                </form>
                            </div>
                        @endif
                    </div>
                    @if ($site->isConnected() && $isOwner)
                        <form method="POST" action="{{ route('wordpress.sites.update', $site) }}" class="mt-4 flex items-start justify-between gap-4 rounded-xl bg-surface-muted p-3">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="auto_send" value="{{ $site->auto_send ? 0 : 1 }}">
                            <div class="text-sm">
                                <p class="font-medium text-navy-900">Send new articles automatically</p>
                                <p class="text-xs text-ink-600">{{ $site->auto_send ? 'Every new blog article arrives here as a draft. Nothing is published without you.' : 'Off: articles go here only when you click "Send to WordPress".' }}</p>
                            </div>
                            <button type="submit" role="switch" aria-checked="{{ $site->auto_send ? 'true' : 'false' }}" aria-label="Send new articles automatically"
                                    class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 {{ $site->auto_send ? 'bg-emerald-500' : 'bg-ink-400/40' }}">
                                <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition {{ $site->auto_send ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
                            </button>
                        </form>
                    @endif
                </x-ui.card>
            @empty
                <div class="rounded-2xl border border-dashed border-line bg-surface p-8 text-center">
                    <x-heroicon-o-globe-alt class="mx-auto h-10 w-10 text-ink-400" />
                    <p class="mt-3 font-display font-semibold text-navy-900">No WordPress sites yet</p>
                    <p class="mx-auto mt-1 max-w-md text-sm text-ink-600">Install the AffilStack Connector plugin on your site and click "Connect to AffilStack". It takes about two minutes.</p>
                </div>
            @endforelse

            @if ($atLimit)
                <p class="text-xs text-amber-700">You've connected the maximum for your plan. Disconnect a site or <a href="{{ route('billing.index') }}" class="underline">upgrade</a> to add another.</p>
            @endif
        </div>

        <x-ui.card title="Connect a site" description="About two minutes, no keys to copy." icon="bolt">
            <ol class="space-y-4 text-sm">
                @foreach ([
                    ['title' => 'Download the plugin', 'body' => 'Click "Download the plugin" above to get affilstack-connector.zip (version '.$version.').'],
                    ['title' => 'Install it in WordPress', 'body' => 'Plugins → Add New → Upload Plugin, choose the zip, then Activate.'],
                    ['title' => 'Connect', 'body' => 'Go to Settings → AffilStack and click "Connect to AffilStack". Approve the site here and you\'re done.'],
                    ['title' => 'Send articles', 'body' => 'Open any blog article in AffilStack and click "Send to WordPress", or switch on automatic sending above.'],
                ] as $step)
                    <li class="flex gap-3">
                        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[11px] font-semibold text-white">{{ $loop->iteration }}</span>
                        <span><span class="block font-medium text-navy-900">{{ $step['title'] }}</span><span class="text-ink-600">{{ $step['body'] }}</span></span>
                    </li>
                @endforeach
            </ol>
            <p class="mt-5 rounded-xl bg-surface-muted p-3 text-xs text-ink-600">Works with Yoast SEO, Rank Math and All in One SEO. Updates appear in WordPress like any other plugin.</p>
        </x-ui.card>
    </div>
@endsection
