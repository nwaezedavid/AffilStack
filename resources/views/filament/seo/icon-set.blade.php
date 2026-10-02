{{-- The generated favicon set, rendered from the real served URLs. Inline styles: see search-appearance.blade.php. --}}
<?php
    $items = [
        ['file' => 'favicon.ico', 'label' => 'favicon.ico', 'note' => '16 · 32 · 48 px', 'show' => 32],
        ['file' => 'favicon-48x48.png', 'label' => '48×48', 'note' => 'Google results', 'show' => 48],
        ['file' => 'favicon-96x96.png', 'label' => '96×96', 'note' => 'High-DPI tabs', 'show' => 64],
        ['file' => 'apple-touch-icon.png', 'label' => '180×180', 'note' => 'iPhone home screen', 'show' => 72],
        ['file' => 'favicon-192x192.png', 'label' => '192×192', 'note' => 'Android / manifest', 'show' => 80],
        ['file' => 'favicon-512x512.png', 'label' => '512×512', 'note' => 'Manifest & logo', 'show' => 96],
    ];
    $meta = $favicons->meta();
?>
<div style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;">
    @foreach ($items as $item)
        <a href="{{ $favicons->url($item['file']) }}" target="_blank" rel="noopener" style="text-align: center; text-decoration: none; color: inherit; border: 1px solid rgba(127,127,127,.25); border-radius: 12px; padding: 12px; min-width: 112px;">
            <span style="display: flex; height: 100px; align-items: center; justify-content: center; background-image: linear-gradient(45deg, rgba(127,127,127,.12) 25%, transparent 25%, transparent 75%, rgba(127,127,127,.12) 75%), linear-gradient(45deg, rgba(127,127,127,.12) 25%, transparent 25%, transparent 75%, rgba(127,127,127,.12) 75%); background-size: 12px 12px; background-position: 0 0, 6px 6px; border-radius: 8px;">
                <img src="{{ $favicons->url($item['file']) }}" alt="{{ $item['label'] }} icon" width="{{ $item['show'] }}" height="{{ $item['show'] }}" style="width: {{ $item['show'] }}px; height: {{ $item['show'] }}px;">
            </span>
            <span style="display: block; font-weight: 600; font-size: 13px; margin-top: 8px;">{{ $item['label'] }}</span>
            <span style="display: block; font-size: 11px; opacity: .65;">{{ $item['note'] }}</span>
        </a>
    @endforeach
</div>
<p style="font-size: 12px; opacity: .7; margin-top: 12px;">
    Also served: <a href="{{ $favicons->url('site.webmanifest') }}" target="_blank" rel="noopener" style="text-decoration: underline;">/site.webmanifest</a>.
    @if ($meta)
        Last built {{ \Illuminate\Support\Carbon::parse($meta['generated_at'])->diffForHumans() }} from {{ $meta['source'] === 'upload' ? 'your uploaded icon' : 'the default monogram' }}.
    @else
        Built automatically on the first request.
    @endif
</p>
