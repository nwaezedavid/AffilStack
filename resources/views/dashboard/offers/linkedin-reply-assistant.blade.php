@extends('layouts.app')

@section('title', 'LinkedIn Reply Assistant')

@section('content')
    <a href="{{ route('offers.show', $offer) }}" class="text-xs text-brand-600 hover:text-brand-700 mb-3 inline-block">&larr; Back to {{ $offer->product_name }}</a>

    <p class="text-sm text-ink-600 max-w-lg mb-6">
        Paste in what a LinkedIn contact actually said back to you, and get a draft reply written to build their
        confidence and move the conversation toward a sale. Copy it, tweak it if you'd like, and send it yourself —
        AffilStack never messages anyone on your behalf.
    </p>

    <div class="bg-surface border border-line rounded-lg p-5 mb-6">
        <form method="POST" action="{{ route('offers.linkedin.reply-assistant.store', $offer) }}">
            @csrf
            <label class="block text-xs uppercase font-mono text-ink-400 mb-1">Their message</label>
            <textarea name="their_message" rows="4" required class="w-full rounded-md border border-line px-3 py-2 text-sm mb-3" placeholder="Paste what they replied with...">{{ old('their_message') }}</textarea>
            <button class="rounded-md border border-line text-ink-900 text-sm px-3 py-2 hover:bg-surface-muted transition">Draft a reply ({{ config('credits.costs.linkedin_reply_draft') }} credits)</button>
        </form>
    </div>

    @if ($drafts->isNotEmpty())
        <h3 class="font-display font-semibold text-sm text-navy-900 mb-3">Recent drafts</h3>
        <div class="space-y-4">
            @foreach ($drafts as $draft)
                <div class="bg-surface border border-line rounded-lg p-4">
                    <div class="text-xs uppercase font-mono text-ink-400 mb-1">They said</div>
                    <p class="text-sm text-ink-600 whitespace-pre-line mb-3">{{ $draft->their_message }}</p>
                    <div class="text-xs uppercase font-mono text-ink-400 mb-1">Your draft reply</div>
                    <p class="text-sm text-ink-900 whitespace-pre-line">{{ $draft->draft_reply }}</p>
                    <div class="text-[11px] text-ink-400 mt-2">{{ $draft->created_at->diffForHumans() }}</div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
