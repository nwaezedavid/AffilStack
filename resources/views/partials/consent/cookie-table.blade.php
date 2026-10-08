{{--
    Agent 04: the live list of cookies, appended to the Cookie Policy page
    (marketing/page) — generated from App\Support\Analytics\CookieCatalog,
    the same source as the preferences dialog, so the policy can never
    drift from what the site actually sets.
--}}
<section class="mt-12 border-t border-line pt-10" aria-labelledby="cookie-list-heading">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 id="cookie-list-heading" class="font-display text-xl font-semibold text-navy-900">Cookies we use right now</h2>
            <p class="mt-1 text-sm text-ink-600">This list is generated from the site's live settings, so it always matches what is actually in use.</p>
        </div>
        <button type="button" data-cookie-settings class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
            <x-heroicon-o-adjustments-horizontal class="h-4 w-4" aria-hidden="true" /> Change my cookie settings
        </button>
    </div>

    @foreach (\App\Support\Analytics\CookieCatalog::categories() as $key => $category)
        <div class="mt-8">
            <h3 class="flex flex-wrap items-center gap-2 font-display text-lg font-semibold text-navy-900">
                {{ $category['label'] }}
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $category['required'] ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-brand-50 text-brand-700 ring-1 ring-brand-100' }}">
                    {{ $category['required'] ? 'Always on' : 'Only with your consent' }}
                </span>
            </h3>
            <p class="mt-1 text-sm text-ink-600">{{ $category['description'] }}</p>

            @if (count($category['cookies']))
                <div class="mt-3 overflow-x-auto rounded-xl ring-1 ring-line">
                    <table class="w-full min-w-[34rem] text-left text-sm">
                        <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-600">
                            <tr>
                                <th scope="col" class="px-4 py-2.5 font-semibold">Cookie</th>
                                <th scope="col" class="px-4 py-2.5 font-semibold">Set by</th>
                                <th scope="col" class="px-4 py-2.5 font-semibold">Purpose</th>
                                <th scope="col" class="px-4 py-2.5 font-semibold">Kept for</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($category['cookies'] as $cookie)
                                <tr class="align-top">
                                    <td class="px-4 py-3 font-mono text-xs font-semibold text-navy-900">{{ $cookie['name'] }}</td>
                                    <td class="px-4 py-3 text-ink-600">{{ $cookie['provider'] }}</td>
                                    <td class="px-4 py-3 text-ink-600">{{ $cookie['purpose'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-ink-600">{{ $cookie['duration'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mt-3 text-sm text-ink-400">None in use at the moment.</p>
            @endif
        </div>
    @endforeach
</section>
