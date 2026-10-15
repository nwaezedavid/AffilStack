@php
    $textLinks = $swipes->get('text_link', collect());
    $descriptions = config('affiliate_kit.descriptions');
    $storage = \Illuminate\Support\Facades\Storage::disk('public');
@endphp

<x-ui.card title="Text links" description="For reviews, tutorials, resource pages and forum signatures. Pick a format, copy, paste." icon="link">
    @if ($textLinks->isEmpty())
        <p class="text-sm text-ink-600">No text links yet. Your plain link is on the Overview tab.</p>
    @else
        <div class="space-y-4">
            @foreach ($textLinks as $textLink)
                @php
                    $anchor = $textLink['body'];
                    $formats = [
                        'html' => '<a href="'.e($textLink['link']).'" target="_blank" rel="sponsored noopener">'.e($anchor).'</a>',
                        'markdown' => '['.$anchor.']('.$textLink['link'].')',
                        'bbcode' => '[url='.$textLink['link'].']'.$anchor.'[/url]',
                    ];
                @endphp
                <div class="rounded-xl border border-line p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h4 class="font-display text-sm font-semibold text-navy-900">{{ $textLink['title'] }}</h4>
                        <a href="{{ $textLink['link'] }}" target="_blank" rel="noopener" class="text-sm font-medium text-brand-600 underline decoration-brand-100 underline-offset-4 hover:decoration-brand-600">{{ $anchor }}</a>
                    </div>
                    <div class="mt-3 grid gap-2 md:grid-cols-3">
                        @foreach ($formats as $format => $snippet)
                            <div class="flex min-w-0 items-center gap-2 rounded-lg bg-surface-muted px-2.5 py-2">
                                <span class="w-16 shrink-0 text-[10px] font-mono uppercase tracking-wide text-ink-400">{{ $format }}</span>
                                <code id="text-link-{{ $textLink['id'] }}-{{ $format }}" class="min-w-0 flex-1 truncate font-mono text-[11px] text-navy-900">{{ $snippet }}</code>
                                <x-ui.copy-button :text="$snippet" label="Copy" size="xs" class="shrink-0" />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-ui.card>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <x-ui.card title="Logos" description="Use these as they are: don't stretch, recolour or redraw them." icon="sparkles">
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach ([
                ['asset' => 'wordmark-dark', 'label' => 'Logo for light backgrounds', 'bg' => 'bg-white'],
                ['asset' => 'wordmark-light', 'label' => 'Logo for dark backgrounds', 'bg' => 'bg-navy-900'],
                ['asset' => 'icon', 'label' => 'App icon', 'bg' => 'bg-surface-muted'],
            ] as $logo)
                <div class="flex flex-col rounded-xl border border-line overflow-hidden">
                    <div class="flex h-24 items-center justify-center {{ $logo['bg'] }} p-3">
                        <img src="{{ route('affiliate-banners.brand-asset', ['asset' => $logo['asset'], 'format' => 'svg']) }}" alt="{{ $logo['label'] }}" class="max-h-14 w-auto max-w-full">
                    </div>
                    <div class="p-3">
                        <p class="text-xs font-medium text-navy-900">{{ $logo['label'] }}</p>
                        <div class="mt-2 flex gap-1.5">
                            <a href="{{ route('affiliate-banners.brand-asset', ['asset' => $logo['asset'], 'format' => 'svg']) }}?download=1" class="rounded-full bg-white px-2.5 py-1 text-[11px] font-medium text-navy-900 ring-1 ring-line hover:bg-surface-muted">SVG</a>
                            <a href="{{ route('affiliate-banners.brand-asset', ['asset' => $logo['asset'], 'format' => 'png']) }}?download=1" class="rounded-full bg-white px-2.5 py-1 text-[11px] font-medium text-navy-900 ring-1 ring-line hover:bg-surface-muted">PNG</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @php($logoFiles = array_filter([
            'Wide logo' => $brand['logo_rectangular'],
            'Square logo' => $brand['logo_square'],
            'White wide logo' => $brand['logo_rectangular_white'] ?? null,
            'White square logo' => $brand['logo_square_white'] ?? null,
        ]))
        @if ($logoFiles)
            <div class="mt-4 border-t border-line pt-4">
                <p class="text-xs font-medium text-ink-900 mb-2">Official {{ $brand['site_name'] }} logo files</p>
                <div class="flex flex-wrap gap-3">
                    @foreach ($logoFiles as $label => $path)
                        <a href="{{ $storage->url($path) }}" download class="flex items-center gap-3 rounded-xl border border-line p-2 pr-4 hover:bg-surface-muted">
                            <img src="{{ $storage->url($path) }}" alt="{{ $label }}" @class(['h-10 w-auto max-w-24 rounded object-contain', 'bg-navy-900 p-1' => str_starts_with($label, 'White'), 'bg-surface-muted' => ! str_starts_with($label, 'White')])>
                            <span class="text-xs font-medium text-navy-900">{{ $label }} <x-heroicon-m-arrow-down-tray class="inline h-3.5 w-3.5" /></span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card title="Brand colours" description="Match your own graphics to AffilStack's look." icon="swatch">
        <div class="grid grid-cols-2 gap-3">
            @foreach ($brand['colors'] as $color)
                <div class="flex items-center gap-3 rounded-xl border border-line p-3">
                    <span class="h-10 w-10 shrink-0 rounded-lg ring-1 ring-line" style="background: {{ $color['hex'] }}"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-navy-900">{{ $color['name'] }}</p>
                        <p class="font-mono text-xs text-ink-600">{{ $color['hex'] }}</p>
                    </div>
                    <x-ui.copy-button :text="$color['hex']" label="Copy" size="xs" class="shrink-0" />
                </div>
            @endforeach
        </div>
        <p class="mt-4 text-xs text-ink-600">Headings use <strong>Space Grotesk</strong>; banners use <strong>Onest</strong>. Both are free Google Fonts.</p>
    </x-ui.card>
</div>

<x-ui.card class="mt-6" title="Product descriptions" description="Short and long descriptions for reviews, directories, bios and video descriptions." icon="document-text">
    <div class="space-y-4">
        @foreach (['tagline' => 'Tagline', 'short' => 'Short description (one sentence)', 'long' => 'Long description (one paragraph)'] as $key => $label)
            <div class="rounded-xl bg-surface-muted p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-medium text-ink-900">{{ $label }}</p>
                    <x-ui.copy-button :target="'kit-description-'.$key" label="Copy" size="xs" />
                </div>
                <p id="kit-description-{{ $key }}" class="mt-2 text-sm leading-relaxed text-ink-900">{{ $descriptions[$key] }}</p>
            </div>
        @endforeach
        <div class="rounded-xl bg-surface-muted p-4">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-medium text-ink-900">Email signature line</p>
                <x-ui.copy-button :target="'kit-signature'" label="Copy" size="xs" />
            </div>
            <p id="kit-signature" class="mt-2 text-sm text-ink-900">P.S. I run my affiliate marketing with AffilStack: {{ $links['email'] }} (affiliate link)</p>
        </div>
    </div>
</x-ui.card>

<x-ui.next-step class="mt-6" :href="$kitTabUrl('overview')" action="See your results" data-tab-link="overview">
    Links in place? Your clicks, sign-ups and commissions update on the Overview tab as soon as people start clicking.
</x-ui.next-step>
