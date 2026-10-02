{{-- Expects $text: the raw (uncloaked) generated text for one public-facing
     field. Shows whether AffilStack had to add disclosure wording or the
     AI already included something that reads as one. --}}
@if ($offer->hasLinkPlaceholder($text ?? null))
    @if ($offer->needsDisclosure($text))
        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 ring-1 ring-amber-200"><x-heroicon-m-shield-exclamation class="h-3.5 w-3.5" /> Disclosure auto-added</span>
    @else
        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-200"><x-heroicon-m-shield-check class="h-3.5 w-3.5" /> Disclosure included</span>
    @endif
@endif
