{{-- One add-on in a marketplace grid. Needs $addon and $href; optional $installed. --}}
@php($version = $addon->liveVersion)
<a href="{{ $href }}" class="group flex h-full flex-col rounded-2xl border border-line bg-surface p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04)] transition hover:-translate-y-0.5 hover:border-brand-500/40 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
    <div class="flex items-start gap-3">
        @include('partials.marketplace.addon-icon', ['version' => $version])
        <div class="min-w-0 flex-1">
            <p class="truncate font-display text-base font-semibold text-navy-900 group-hover:text-brand-700">{{ $version->name }}</p>
            <p class="truncate text-xs text-ink-400">by {{ $addon->developer?->display_name }}</p>
        </div>
        @if ($addon->is_featured)
            <span class="rounded-full bg-gold-300/30 px-2 py-0.5 text-[11px] font-semibold text-gold-700">Featured</span>
        @endif
    </div>
    <p class="mt-3 line-clamp-2 flex-1 text-sm text-ink-600">{{ $version->tagline }}</p>
    <div class="mt-4 flex flex-wrap items-center gap-2 text-[11px] font-medium">
        <span class="rounded-full bg-surface-muted px-2.5 py-1 text-ink-600 ring-1 ring-line">{{ $addon->typeLabel() }}</span>
        <span class="rounded-full px-2.5 py-1 ring-1 {{ $version->isPaid() ? 'bg-brand-50 text-brand-700 ring-brand-500/20' : 'bg-emerald-50 text-emerald-700 ring-emerald-500/20' }}">{{ $version->priceLabel() }}</span>
        @if (! empty($installed))
            <span class="ml-auto inline-flex items-center gap-1 text-emerald-700"><x-heroicon-m-check-circle class="h-4 w-4" aria-hidden="true" /> Added</span>
        @endif
    </div>
</a>
