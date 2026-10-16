{{-- Shown on English website pages to visitors whose browser prefers another live language (see LocalizeResponse). Already in that language. --}}
<div translate="no" lang="{{ $code }}" role="region" aria-label="{{ $question }}"
     style="position:fixed;left:50%;top:76px;transform:translateX(-50%);z-index:60;width:max-content;max-width:calc(100% - 32px)"
     class="flex flex-wrap items-center justify-center gap-x-3 gap-y-2 rounded-2xl border border-line bg-white px-4 py-3 text-sm text-navy-900 shadow-lg">
    <span class="flex items-center gap-2 font-medium">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4 text-brand-600"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0c2.5-2.4 3.75-5.4 3.75-9S14.5 5.4 12 3m0 18c-2.5-2.4-3.75-5.4-3.75-9S9.5 5.4 12 3M3.5 9h17M3.5 15h17"/></svg>
        {{ $question }}
    </span>
    <span class="flex items-center gap-2">
        <a href="{{ $switchUrl }}" data-locale-link class="whitespace-nowrap rounded-full bg-navy-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-navy-800">{{ $yes }}</a>
        <a href="{{ $stayUrl }}" data-locale-link class="whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-medium text-ink-600 hover:text-navy-900">{{ $no }}</a>
    </span>
</div>
