@props(['trail'])
{{--
    $trail: array<int, array{label: string, url: ?string}> — the last entry
    (the current page) should have url => null. Renders both the visible
    breadcrumb nav and its BreadcrumbList schema together, so the two can
    never drift out of sync with each other.
--}}
<?php
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($trail)->values()->map(fn ($item, $i) => array_filter([
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $item['label'],
            'item' => $item['url'] ?? null,
        ]))->all(),
    ];
?>
<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<nav aria-label="Breadcrumb" class="text-xs text-ink-400 mb-4">
    @foreach ($trail as $i => $item)
        @if (! $loop->first)
            <span class="mx-1.5">/</span>
        @endif
        @if ($item['url'] ?? null)
            <a href="{{ $item['url'] }}" class="hover:text-ink-600 hover:underline">{{ $item['label'] }}</a>
        @else
            <span aria-current="page">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
