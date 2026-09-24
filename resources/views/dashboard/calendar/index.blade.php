@extends('layouts.app')

@section('title', 'Content Calendar')

@section('content')
    <p class="text-sm text-ink-600 max-w-lg mb-6">One view of everything you've generated across every channel, with a status and a target date — plus reminders for blog refreshes and LinkedIn follow-ups, computed from what you've actually published. AffilStack never posts on your behalf, so nothing here fires automatically; it's a planning view, not an autopilot.</p>

    @if (! empty($reminders))
        <h3 class="font-display font-semibold text-sm text-navy-900 mb-3">Upcoming reminders</h3>
        <div class="space-y-2 mb-8">
            @foreach ($reminders as $reminder)
                <div class="bg-surface border rounded-lg p-4 flex items-center justify-between gap-4 {{ $reminder['overdue'] ? 'border-red-200' : 'border-line' }}">
                    <div>
                        <div class="text-sm font-medium text-ink-900">{{ $reminder['title'] }}</div>
                        <div class="text-xs text-ink-600">{{ $reminder['offer_name'] }} &middot; {{ $reminder['detail'] }}</div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-xs font-mono px-2 py-1 rounded {{ $reminder['overdue'] ? 'bg-red-50 text-red-700' : 'bg-gold-100 text-gold-800' }}">
                            {{ $reminder['overdue'] ? 'Overdue' : 'Due' }} {{ $reminder['due']->diffForHumans() }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <h3 class="font-display font-semibold text-sm text-navy-900 mb-3">All content</h3>

    @if ($entries->isEmpty())
        <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600">
            Nothing generated yet — content you create from an offer's page (blog articles, LinkedIn posts/articles, YouTube scripts, UGC, X threads, TikTok videos) shows up here automatically.
        </div>
    @else
        <div class="bg-surface border border-line rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-4 py-2.5">Offer</th>
                        <th class="text-left px-4 py-2.5">Channel</th>
                        <th class="text-left px-4 py-2.5">Title</th>
                        <th class="text-left px-4 py-2.5">Status</th>
                        <th class="text-left px-4 py-2.5">Scheduled for</th>
                        <th class="text-left px-4 py-2.5">Published</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($entries as $entry)
                        <tr>
                            <td class="px-4 py-3 text-ink-900">{{ $entry->offer->product_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-600 capitalize">
                                {{ str_replace('_', ' ', $entry->module) }}
                                @if ($entry->target_market)
                                    <span class="ml-1" title="Localized for {{ config('localization.markets.'.$entry->target_market.'.label') }}">{{ config('localization.markets.'.$entry->target_market.'.flag') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-ink-900">{{ $entry->calendarTitle() }}</td>
                            <td class="px-4 py-3" colspan="4">
                                <form method="POST" action="{{ route('calendar.update', $entry) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="calendar_status" class="text-xs rounded border border-line px-2 py-1">
                                        @foreach (['draft', 'scheduled', 'published', 'skipped'] as $status)
                                            {{-- Team seats are "draft only, no publish" (item 10) — the
                                                 status transition is also blocked server-side. --}}
                                            @if ($status !== 'published' || ! auth()->user()->isSeat())
                                                <option value="{{ $status }}" @selected($entry->calendar_status === $status)>{{ ucfirst($status) }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <input type="date" name="scheduled_for" value="{{ $entry->scheduled_for?->format('Y-m-d') }}" class="text-xs rounded border border-line px-2 py-1">
                                    <span class="text-xs text-ink-400 font-mono">{{ $entry->published_at?->format('M j, Y') ?? '—' }}</span>
                                    <button class="text-xs rounded-md border border-line px-2.5 py-1 hover:bg-surface-muted transition">Save</button>
                                    <a href="{{ route('offers.show', $entry->offer_id) }}" class="text-xs text-brand-600 hover:text-brand-700 ml-auto">View &rarr;</a>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif
@endsection
