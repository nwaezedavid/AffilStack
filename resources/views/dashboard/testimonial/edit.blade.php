@extends('layouts.app')

@section('title', 'Share a Review')

@section('content')
    <p class="text-sm text-ink-600 max-w-lg mb-6">
        Tell other affiliate marketers what AffilStack has done for you. We review every submission before it goes
        live — approved reviews appear on the homepage once we have at least a few published.
    </p>

    @if ($testimonial)
        <div class="max-w-lg mb-6 rounded-lg border px-4 py-3 text-sm
            {{ match ($testimonial->status) {
                'approved' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
                'declined' => 'bg-red-50 border-red-200 text-red-700',
                default => 'bg-amber-50 border-amber-200 text-amber-800',
            } }}">
            @if ($testimonial->status === 'approved' && $testimonial->is_published)
                ✓ Live on the homepage. Editing and resubmitting below will send it back for another review before
                it stays visible.
            @elseif ($testimonial->status === 'approved')
                ✓ Approved — it'll appear on the homepage once we have enough published reviews.
            @elseif ($testimonial->status === 'declined')
                This one wasn't approved for the homepage. Feel free to update it and resubmit.
            @else
                Pending review — we'll let you know once it's been looked at.
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('testimonial.update') }}" class="max-w-lg space-y-4">
        @csrf

        <div>
            <label class="block text-xs font-medium text-ink-600 mb-1">Your name</label>
            <input type="text" name="author_name"
                   value="{{ old('author_name', $testimonial->author_name ?? auth()->user()->name) }}"
                   required maxlength="255"
                   class="w-full rounded-md border border-line px-2.5 py-2 text-sm">
            @error('author_name')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-ink-600 mb-1">Role or company (optional)</label>
            <input type="text" name="author_role"
                   value="{{ old('author_role', $testimonial->author_role ?? '') }}"
                   maxlength="255" placeholder="e.g. Affiliate marketer, or &quot;Founder, Acme Co.&quot;"
                   class="w-full rounded-md border border-line px-2.5 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-medium text-ink-600 mb-1">Rating</label>
            @php $currentRating = (int) old('rating', $testimonial->rating ?? 5); @endphp
            <select name="rating" class="w-full rounded-md border border-line px-2.5 py-2 text-sm">
                @for ($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}" @selected($currentRating === $i)>
                        {{ str_repeat('★', $i).str_repeat('☆', 5 - $i) }}
                    </option>
                @endfor
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-ink-600 mb-1">Your review</label>
            <textarea name="quote" required rows="4" maxlength="1000"
                      class="w-full rounded-md border border-line px-2.5 py-2 text-sm"
                      placeholder="What difference has AffilStack made for you?">{{ old('quote', $testimonial->quote ?? '') }}</textarea>
            @error('quote')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <button class="rounded-md bg-navy-900 text-white text-sm px-4 py-2 hover:bg-navy-800 transition">
            {{ $testimonial ? 'Update & resubmit for review' : 'Submit for review' }}
        </button>
    </form>
@endsection
