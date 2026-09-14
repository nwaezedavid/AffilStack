{{-- Expects $text: the raw (uncloaked) generated text for one public-facing
     field. Shows whether AffilStack had to add disclosure wording or the
     AI already included something that reads as one. --}}
@if ($offer->hasLinkPlaceholder($text ?? null))
    @if ($offer->needsDisclosure($text))
        <span class="text-xs px-2 py-0.5 rounded-full bg-gold-100 text-gold-800 font-mono whitespace-nowrap">⚠ disclosure auto-added</span>
    @else
        <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-mono whitespace-nowrap">✓ disclosure included</span>
    @endif
@endif
