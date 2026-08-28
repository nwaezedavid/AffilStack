@extends('layouts.app')

@section('title', 'Support')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-ink-600">Ask the AI assistant first, or open a ticket for a human.</p>
        <div class="flex gap-2">
            <a href="{{ route('support.chat') }}" class="rounded-md border border-line text-ink-900 text-sm px-3 py-1.5 hover:bg-surface-muted transition">💬 Chat with AI assistant</a>
            <a href="{{ route('support.create') }}" class="rounded-md bg-navy-900 text-white text-sm px-3 py-1.5 hover:bg-navy-800 transition">Open a ticket</a>
        </div>
    </div>

    @if ($tickets->isEmpty())
        <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600">
            No support tickets yet.
        </div>
    @else
        <div class="space-y-3">
            @foreach ($tickets as $ticket)
                <a href="{{ route('support.show', $ticket) }}" class="block bg-surface border border-line rounded-lg p-4 hover:border-brand-500 transition">
                    <div class="flex items-center justify-between gap-3">
                        <div class="font-medium text-ink-900 text-sm">{{ $ticket->subject }}</div>
                        <span @class([
                            'text-xs font-mono uppercase px-2 py-0.5 rounded',
                            'bg-emerald-50 text-emerald-700' => $ticket->status === 'resolved',
                            'bg-surface-muted text-ink-600' => $ticket->status === 'closed',
                            'bg-amber-50 text-amber-700' => $ticket->status === 'pending',
                            'bg-blue-50 text-blue-700' => $ticket->status === 'open',
                        ])>{{ $ticket->status }}</span>
                    </div>
                    <div class="text-xs text-ink-400 mt-1">{{ ucfirst($ticket->category) }} · {{ ucfirst($ticket->priority) }} priority · last activity {{ $ticket->last_reply_at?->diffForHumans() }}</div>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $tickets->links() }}</div>
    @endif
@endsection
