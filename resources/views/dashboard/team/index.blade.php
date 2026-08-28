@extends('layouts.app')

@section('title', 'Team')

@section('content')
    @php
        $user = auth()->user();
        $maxSeats = $user->maxAgencySeats();
        $used = $seats->count();
        $remaining = $user->agencySeatsRemaining();
    @endphp

    <p class="text-sm text-ink-600 max-w-lg mb-6">Give a VA their own login, scoped to exactly one product. They can generate content and plan the calendar for it — they can't publish, touch billing/CRM/earnings, or see anything outside that one offer. Every credit they use is billed to your account.</p>

    <div class="bg-surface border border-line rounded-lg p-4 mb-6 flex items-center gap-3 flex-wrap">
        <span class="text-xs font-mono uppercase tracking-wide text-ink-400">Seats used</span>
        <span class="text-sm font-medium text-ink-900">{{ $used }} of {{ $maxSeats - 1 }} included in your plan</span>
        @if ($maxSeats <= 1)
            <a href="{{ route('billing.index') }}" class="text-xs text-brand-600 hover:text-brand-700 underline ml-auto">Your current plan doesn't include team seats — upgrade to Pro or Agency &rarr;</a>
        @elseif ($remaining < 1)
            <a href="{{ route('billing.index') }}" class="text-xs text-brand-600 hover:text-brand-700 underline ml-auto">All seats used — upgrade for more &rarr;</a>
        @endif
    </div>

    @if ($remaining >= 1)
        @if ($offers->isEmpty())
            <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600 mb-6">
                Add an offer first — a seat has to be scoped to one of your products.
            </div>
        @else
            <details class="bg-surface border border-line rounded-lg p-5 mb-6">
                <summary class="cursor-pointer text-sm font-medium text-ink-900">+ Add a team member</summary>
                <form method="POST" action="{{ route('team.store') }}" class="grid sm:grid-cols-2 gap-3 mt-4">
                    @csrf
                    <input name="name" placeholder="Name" required class="rounded-md border border-line px-3 py-2 text-sm">
                    <input name="email" type="email" placeholder="Email" required class="rounded-md border border-line px-3 py-2 text-sm">
                    <select name="offer_id" required class="rounded-md border border-line px-3 py-2 text-sm sm:col-span-2">
                        <option value="">Scope to which product?</option>
                        @foreach ($offers as $offer)
                            <option value="{{ $offer->id }}">{{ $offer->product_name }}</option>
                        @endforeach
                    </select>
                    <button class="sm:col-span-2 rounded-md bg-navy-900 text-white text-sm py-2 hover:bg-navy-800 transition">Create seat</button>
                </form>
            </details>
        @endif
    @endif

    @if ($seats->isEmpty())
        <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600">
            No team members yet.
        </div>
    @else
        <div class="bg-surface border border-line rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-4 py-2.5">Name</th>
                        <th class="text-left px-4 py-2.5">Email</th>
                        <th class="text-left px-4 py-2.5">Scoped to</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($seats as $seat)
                        <tr>
                            <td class="px-4 py-3 font-medium text-ink-900">{{ $seat->name }}</td>
                            <td class="px-4 py-3 text-ink-600">{{ $seat->email }}</td>
                            <td class="px-4 py-3 text-ink-900">{{ $seat->seatOffer?->product_name ?? 'Offer deleted' }}</td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('team.destroy', $seat) }}" onsubmit="return confirm('Remove this team member? They\'ll lose access immediately — their drafts stay under your account.')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-600 hover:text-red-700">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
