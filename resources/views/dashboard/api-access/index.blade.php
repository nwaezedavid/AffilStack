@extends('layouts.app')

@section('title', 'API & Integrations')

@section('content')
    @php
        $isSeat = auth()->user()->isSeat();
    @endphp

    <x-ui.page-header
        eyebrow="Developers & automations"
        title="API & Integrations"
        subtitle="Connect AffilStack to Zapier, Make, n8n, Google Sheets, WordPress or your own code — send new contacts out, pull generated content in, and start product research automatically."
        icon="code-bracket-square"
    >
        <x-slot:actions>
            <x-ui.button variant="gold" :href="route('api-access.index', ['tab' => 'quick-start'])" icon="key" data-tab-link="quick-start">Create a token</x-ui.button>
            <x-ui.button variant="light" :href="$openApiUrl" icon="arrow-down-tray">OpenAPI spec</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="lg:grid lg:grid-cols-[13.5rem_minmax(0,1fr)] lg:gap-6">
        {{-- Sub-navigation: real ?tab= links (work without JavaScript), upgraded to instant switching below. --}}
        <nav aria-label="API & Integrations sections" class="mb-5 lg:mb-0">
            <ul class="-mx-4 flex gap-1.5 overflow-x-auto px-4 pb-1 lg:sticky lg:top-6 lg:mx-0 lg:flex-col lg:gap-0.5 lg:overflow-visible lg:px-0 lg:pb-0">
                @foreach ($tabs as $key => $tab)
                    <li class="shrink-0">
                        <a href="{{ route('api-access.index', ['tab' => $key]) }}" data-tab-link="{{ $key }}"
                            @if ($activeTab === $key) aria-current="page" @endif
                            class="group flex items-center gap-2.5 whitespace-nowrap rounded-full px-3.5 py-2 text-sm font-medium ring-1 ring-inset ring-line text-ink-600 transition hover:bg-surface hover:text-navy-900 lg:rounded-xl lg:ring-0 aria-[current=page]:bg-navy-900 aria-[current=page]:text-white aria-[current=page]:ring-navy-900 aria-[current=page]:shadow-sm">
                            <x-dynamic-component :component="'heroicon-o-'.$tab['icon']" class="h-4 w-4 shrink-0 opacity-80" />
                            {{ $tab['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-5 hidden rounded-2xl border border-line bg-surface p-4 lg:block">
                <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-400">Your base URL</p>
                <p class="mt-1 break-all font-mono text-xs text-navy-900">{{ $baseUrl }}</p>
                <button type="button" data-copy-text="{{ $baseUrl }}" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700">
                    <x-heroicon-m-clipboard-document class="h-3.5 w-3.5" /> <span data-copy-label>Copy</span>
                </button>
            </div>
        </nav>

        <div class="min-w-0">
            @foreach (array_keys($tabs) as $key)
                <section id="tab-{{ $key }}" data-tab-panel="{{ $key }}" @class(['space-y-6', 'hidden' => $activeTab !== $key]) aria-label="{{ $tabs[$key]['label'] }}">
                    @include('dashboard.api-access.partials.'.$key)
                </section>
            @endforeach
        </div>
    </div>

    @push('scripts')
        <script>
            // Instant tab switching; the URL keeps ?tab= so refreshes and form redirects land on the same tab.
            (function () {
                const panels = document.querySelectorAll('[data-tab-panel]');
                function show(tab, scroll) {
                    if (!document.querySelector('[data-tab-panel="' + tab + '"]')) {
                        return false;
                    }
                    panels.forEach(function (panel) { panel.classList.toggle('hidden', panel.dataset.tabPanel !== tab); });
                    document.querySelectorAll('nav [data-tab-link]').forEach(function (link) {
                        if (link.dataset.tabLink === tab) { link.setAttribute('aria-current', 'page'); } else { link.removeAttribute('aria-current'); }
                    });
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', tab);
                    history.replaceState(null, '', url.pathname + url.search + url.hash);
                    if (scroll) { window.scrollTo({ top: 0, behavior: 'smooth' }); }
                    return true;
                }
                document.addEventListener('click', function (event) {
                    const link = event.target.closest('[data-tab-link]');
                    if (!link || event.metaKey || event.ctrlKey) {
                        return;
                    }
                    if (show(link.dataset.tabLink, true)) {
                        event.preventDefault();
                        if (link.dataset.target) {
                            const target = document.getElementById(link.dataset.target);
                            if (target) {
                                if (target.tagName === 'DETAILS') { target.open = true; }
                                setTimeout(function () { target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 50);
                            }
                        }
                    }
                });
                window.AffilStackApiHub = { show: show };
            })();
        </script>
    @endpush
@endsection
