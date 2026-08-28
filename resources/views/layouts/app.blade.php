<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · AffiliStack</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-surface-muted text-ink-900 antialiased">
    <div class="min-h-screen flex">
        {{-- Sidebar --}}
        <aside class="w-64 shrink-0 bg-navy-900 text-white flex flex-col">
            <div class="px-5 py-5 border-b border-white/10">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-display font-semibold text-lg">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-white/10 text-gold-400 text-sm">AS</span>
                    AffiliStack
                </a>
            </div>

            <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
                @php
                    $items = [
                        ['name' => 'dashboard', 'match' => 'dashboard', 'label' => 'Overview', 'icon' => '🏠'],
                        ['name' => 'offers.index', 'match' => 'offers.*', 'label' => 'Offer Research', 'icon' => '🔎'],
                        ['name' => 'crm.index', 'match' => 'crm.*', 'label' => 'CRM Contacts', 'icon' => '📇'],
                        ['name' => 'support.index', 'match' => 'support.*', 'label' => 'Support', 'icon' => '💬'],
                        ['name' => 'billing.index', 'match' => 'billing.*', 'label' => 'Billing & Plan', 'icon' => '💳'],
                    ];
                @endphp
                @foreach ($items as $item)
                    <a href="{{ route($item['name']) }}"
                       class="flex items-center gap-3 rounded-md px-3 py-2 transition {{ request()->routeIs($item['match']) ? 'bg-white/10 text-white' : 'text-navy-100/80 hover:bg-white/5 hover:text-white' }}">
                        <span aria-hidden="true">{{ $item['icon'] }}</span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="px-3 py-4 border-t border-white/10 space-y-3">
                <div class="rounded-md bg-white/5 px-3 py-2.5 text-xs">
                    <div class="flex items-center justify-between text-navy-100/70">
                        <span>Credits</span>
                        <span class="font-mono text-gold-400 font-semibold">{{ number_format(auth()->user()->credits_balance) }}</span>
                    </div>
                </div>
                <a href="{{ route('profile') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-navy-100/80 hover:bg-white/5 hover:text-white">
                    <span aria-hidden="true">⚙️</span> Profile & Security
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm text-navy-100/80 hover:bg-white/5 hover:text-white">
                        <span aria-hidden="true">↩</span> Sign out
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main --}}
        <div class="flex-1 min-w-0">
            <header class="bg-surface border-b border-line px-6 py-4 flex items-center justify-between">
                <h1 class="font-display font-semibold text-lg text-ink-900">@yield('title', 'Overview')</h1>
                <div class="flex items-center gap-4">
                    <div id="notif-bell" class="relative">
                        <button type="button" id="notif-toggle" class="relative flex items-center justify-center h-9 w-9 rounded-md text-ink-600 hover:bg-surface-muted transition" aria-label="Notifications">
                            <span aria-hidden="true">🔔</span>
                            <span id="notif-badge" class="hidden absolute -top-1 -right-1 min-w-[16px] h-4 px-1 rounded-full bg-red-600 text-white text-[10px] leading-4 text-center font-mono"></span>
                        </button>
                        <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 max-h-96 overflow-y-auto bg-surface border border-line rounded-lg shadow-lg z-20">
                            <div class="flex items-center justify-between px-4 py-2.5 border-b border-line">
                                <span class="text-xs font-mono uppercase tracking-wide text-ink-400">Notifications</span>
                                <button type="button" id="notif-mark-all" class="text-xs text-brand-600 hover:text-brand-700">Mark all read</button>
                            </div>
                            <div id="notif-list" class="divide-y divide-line-soft">
                                <p class="px-4 py-6 text-center text-xs text-ink-400">No notifications yet.</p>
                            </div>
                        </div>
                    </div>
                    <div class="text-sm text-ink-600">{{ auth()->user()->name }}</div>
                </div>
            </header>

            <main class="p-6 max-w-5xl">
                @if (session('success'))
                    <div class="mb-5 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md px-4 py-2.5">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="mb-5 text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-4 py-2.5">
                        {{ session('error') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mb-5 text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-4 py-2.5">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (function () {
            const toggle = document.getElementById('notif-toggle');
            const dropdown = document.getElementById('notif-dropdown');
            const badge = document.getElementById('notif-badge');
            const list = document.getElementById('notif-list');
            const markAllBtn = document.getElementById('notif-mark-all');
            const pollUrl = @json(route('notifications.poll'));
            const markAllUrl = @json(route('notifications.read-all'));
            const csrf = @json(csrf_token());

            function render(items) {
                if (!items.length) {
                    list.innerHTML = '<p class="px-4 py-6 text-center text-xs text-ink-400">No notifications yet.</p>';
                    return;
                }
                list.innerHTML = items.map(function (n) {
                    const dot = n.read_at ? '' : '<span class="inline-block w-1.5 h-1.5 rounded-full bg-brand-600 mr-1.5 align-middle"></span>';
                    const tone = n.success ? 'text-ink-900' : 'text-red-700';
                    return '<a href="' + n.url + '" data-id="' + n.id + '" class="notif-item block px-4 py-3 hover:bg-surface-muted transition">'
                        + '<div class="text-xs ' + tone + '">' + dot + n.message + '</div>'
                        + '<div class="text-[11px] text-ink-400 mt-0.5">' + n.created_at + '</div>'
                        + '</a>';
                }).join('');
            }

            function poll() {
                fetch(pollUrl, { headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (!data) {
                            return;
                        }
                        if (data.unread_count > 0) {
                            badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                        render(data.items);
                    })
                    .catch(function () {});
            }

            toggle.addEventListener('click', function () {
                dropdown.classList.toggle('hidden');
            });

            document.addEventListener('click', function (e) {
                if (!document.getElementById('notif-bell').contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });

            markAllBtn.addEventListener('click', function () {
                fetch(markAllUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                }).then(poll);
            });

            poll();
            setInterval(poll, 20000);
        })();
    </script>

    @stack('scripts')
</body>
</html>
