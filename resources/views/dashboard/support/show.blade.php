@extends('layouts.app')

@section('title', $ticket->subject)

@section('content')
    <a href="{{ route('support.index') }}" class="text-sm text-ink-400 hover:text-ink-900">&larr; Back to support</a>

    <div class="flex items-center justify-between gap-3 mt-4 mb-6">
        <div>
            <h1 class="text-lg font-semibold text-ink-900">{{ $ticket->subject }}</h1>
            <div class="text-xs text-ink-400 mt-1">{{ ucfirst($ticket->category) }} · {{ ucfirst($ticket->priority) }} priority · opened {{ $ticket->created_at->diffForHumans() }}</div>
        </div>
        <span @class([
            'text-xs font-mono uppercase px-2 py-1 rounded',
            'bg-emerald-50 text-emerald-700' => $ticket->status === 'resolved',
            'bg-surface-muted text-ink-600' => $ticket->status === 'closed',
            'bg-amber-50 text-amber-700' => $ticket->status === 'pending',
            'bg-blue-50 text-blue-700' => $ticket->status === 'open',
        ])>{{ $ticket->status }}</span>
    </div>

    <div class="space-y-4 mb-6">
        @foreach ($ticket->messages as $message)
            <div @class([
                'max-w-2xl rounded-lg p-4 text-sm whitespace-pre-line',
                'ml-auto bg-navy-900 text-white' => ! $message->is_staff,
                'bg-surface border border-line text-ink-900' => $message->is_staff,
            ])>
                <div @class([
                    'text-xs font-medium mb-1',
                    'text-white/70' => ! $message->is_staff,
                    'text-ink-400' => $message->is_staff,
                ])>{{ $message->is_staff ? 'AffiliStack Support' : 'You' }} · {{ $message->created_at->diffForHumans() }}</div>
                {{ $message->message }}
            </div>
        @endforeach
    </div>

    @if (in_array($ticket->status, ['resolved', 'closed'], true))
        <div class="bg-surface border border-line rounded-lg p-4 mb-6">
            @if ($ticket->csat_rating)
                <p class="text-sm text-ink-600">Thanks for your feedback — you rated this {{ $ticket->csat_rating }}/5.</p>
            @else
                <p class="text-sm text-ink-900 font-medium mb-2">This ticket is {{ $ticket->status }}. How did we do?</p>
                <form method="POST" action="{{ route('support.rate', $ticket) }}" class="flex items-center gap-2">
                    @csrf
                    @for ($i = 1; $i <= 5; $i++)
                        <button type="submit" name="csat_rating" value="{{ $i }}" class="text-2xl leading-none hover:scale-110 transition" title="{{ $i }} star{{ $i > 1 ? 's' : '' }}">⭐</button>
                    @endfor
                </form>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('support.reply', $ticket) }}" class="bg-surface border border-line rounded-lg p-4">
        @csrf
        <label class="block text-sm font-medium text-ink-900 mb-1">Add a reply</label>
        <textarea name="message" rows="4" required maxlength="5000" class="w-full rounded-md border-line focus:border-brand-500 focus:ring-brand-500 text-sm"></textarea>
        @error('message') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        <button type="submit" class="mt-3 rounded-md bg-navy-900 text-white text-sm font-medium px-4 py-2 hover:bg-navy-800 transition">
            Send reply
        </button>
    </form>
@endsection
