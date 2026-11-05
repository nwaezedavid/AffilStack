{{-- AI lead finder: B2B buyers found, checked, scored and tagged into this
     product's CRM list. Owner-only — $leads is null for team seats. --}}
@if (($leads ?? null) && ($prospectList ?? null))
    @php
        $run = $leads['run'];
        $listUrl = \Illuminate\Support\Facades\Route::has('crm.lists.show') ? route('crm.lists.show', $prospectList) : route('crm.index');
        $exportUrl = route('crm.export', ['list' => $prospectList->id]);
        $sourceLabels = ['google_maps' => 'Google Maps', 'apollo' => 'Apollo', 'hunter' => 'Hunter'];
    @endphp
    <section data-tour="lead-finder" class="@container rounded-3xl border border-line bg-white p-5 sm:p-6">
        <div class="flex flex-col gap-4 @3xl:flex-row @3xl:items-start @3xl:justify-between">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <x-heroicon-o-user-plus class="h-5 w-5" />
                </span>
                <div>
                    <h4 class="font-display font-semibold text-navy-900">Find B2B buyers for this product</h4>
                    <p class="max-w-2xl text-sm text-ink-600">
                        AffilStack searches for businesses and decision-makers who match this product's buyer, checks their emails,
                        scores how well each one fits and tags them into <strong class="text-navy-900">{{ $prospectList->name }}</strong>.
                        Export them any time for programs that pay for qualified leads.
                    </p>
                    <p class="mt-1 text-xs text-emerald-700">
                        @if ($leads['free_remaining'] === PHP_INT_MAX)
                            Unlimited leads on your plan.
                        @elseif ($leads['free_remaining'] > 0)
                            {{ number_format($leads['free_remaining']) }} free {{ \Illuminate\Support\Str::plural('lead', $leads['free_remaining']) }} left this month, then {{ $leads['cost_per_lead'] }} credits per lead.
                        @else
                            This month's included leads are used — each new lead costs {{ $leads['cost_per_lead'] }} credits.
                        @endif
                    </p>
                </div>
            </div>

            @if ($leads['available'])
                <form method="POST" action="{{ route('offers.leads.store', $offer) }}" class="flex shrink-0 items-center gap-2">
                    @csrf
                    <label for="lead-target" class="sr-only">How many leads</label>
                    <select id="lead-target" name="target" class="rounded-full border border-line bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        @foreach (config('leads.run_sizes') as $size)
                            <option value="{{ $size }}" @selected($size === (int) config('leads.default_run_size'))>{{ $size }} leads</option>
                        @endforeach
                    </select>
                    <button type="submit" @disabled($run?->isActive())
                            class="inline-flex items-center gap-1.5 rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 disabled:opacity-60">
                        <x-heroicon-m-sparkles class="h-4 w-4" />
                        {{ $run?->isActive() ? 'Finding leads…' : 'Find leads' }}
                    </button>
                </form>
            @else
                <p class="shrink-0 rounded-xl bg-surface-muted px-3 py-2 text-xs text-ink-600">Lead finding is being set up. Check back soon.</p>
            @endif
        </div>

        @if ($run)
            <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl bg-surface-muted px-4 py-3 text-sm">
                @if ($run->isActive())
                    <x-ui.status-pill status="checking" label="Finding leads…" />
                    <span class="text-ink-600">Started {{ $run->created_at->diffForHumans() }}. Leads appear in your CRM as soon as the search finishes.</span>
                @else
                    <x-ui.status-pill :status="$run->status === 'completed' && $run->saved > 0 ? 'verified' : 'failed'" :label="$run->status === 'completed' ? 'Last search' : 'Last search stopped'" />
                    <span class="text-ink-600">{{ $run->message }}</span>
                    @if ($run->saved > 0)
                        <span class="text-xs text-ink-400">
                            {{ $run->with_email }} with a checked email
                            @if ($run->sources) · from {{ collect($run->sources)->map(fn ($s) => $sourceLabels[$s] ?? $s)->implode(', ') }} @endif
                            · {{ $run->finished_at?->diffForHumans() }}
                        </span>
                    @endif
                @endif
            </div>
        @endif

        <div class="mt-4 flex flex-wrap gap-2 text-sm">
            <a href="{{ $listUrl }}" class="inline-flex items-center gap-1.5 rounded-full border border-line px-3 py-1.5 font-medium text-navy-900 hover:bg-surface-muted">
                <x-heroicon-m-users class="h-4 w-4 text-ink-400" /> View leads ({{ number_format($prospectCount) }})
            </a>
            <a href="{{ $exportUrl }}" class="inline-flex items-center gap-1.5 rounded-full border border-line px-3 py-1.5 font-medium text-navy-900 hover:bg-surface-muted">
                <x-heroicon-m-arrow-down-tray class="h-4 w-4 text-ink-400" /> Export CSV
            </a>
            <a href="#linkedin-keywords" class="inline-flex items-center gap-1.5 rounded-full border border-line px-3 py-1.5 font-medium text-navy-900 hover:bg-surface-muted">
                <x-ui.brand-icon name="linkedin" class="h-4 w-4" /> LinkedIn searches &amp; message drafts
            </a>
        </div>
        <p class="mt-3 text-xs text-ink-400">Business contacts only. Every lead records where it was found, and every email AffilStack sends includes an unsubscribe link.</p>
    </section>
@endif
