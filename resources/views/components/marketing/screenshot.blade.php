@props(['src', 'alt' => '', 'url' => null, 'eager' => false, 'tone' => 'brand'])

{{-- A product screenshot in a light browser frame with a soft coloured
     glow — the one way screenshots are presented across the marketing
     site (feature pages, homepage "How it works"). --}}
@php
    $glow = [
        'brand' => 'from-brand-500/30 via-violet-500/15 to-gold-400/30',
        'dark' => 'from-brand-500/40 via-violet-500/20 to-gold-400/40',
    ][$tone] ?? 'from-brand-500/30 via-violet-500/15 to-gold-400/30';
    $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'affilstack.com';
@endphp
<figure {{ $attributes->merge(['class' => 'relative']) }}>
    <div class="pointer-events-none absolute -inset-3 rounded-[1.75rem] bg-gradient-to-tr {{ $glow }} blur-2xl" aria-hidden="true"></div>
    <div class="relative overflow-hidden rounded-2xl bg-white shadow-[0_30px_60px_-30px_rgba(11,30,61,.55)] ring-1 ring-navy-900/10">
        <div class="flex items-center gap-2 border-b border-line bg-surface-muted/80 px-4 py-2.5" aria-hidden="true">
            <span class="h-2.5 w-2.5 rounded-full bg-rose-400/80"></span>
            <span class="h-2.5 w-2.5 rounded-full bg-amber-400/80"></span>
            <span class="h-2.5 w-2.5 rounded-full bg-emerald-400/80"></span>
            <span class="ml-3 hidden truncate rounded-md bg-white px-3 py-0.5 font-mono text-[10px] text-ink-400 ring-1 ring-line sm:block">{{ $url ?: $host }}</span>
        </div>
        <x-marketing.feature-media :src="$src" :alt="$alt" :eager="$eager" class="block h-auto w-full" />
    </div>
</figure>
