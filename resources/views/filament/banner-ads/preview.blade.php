<div style="display:flex;flex-wrap:wrap;gap:20px;align-items:flex-start">
    @foreach ($frames as $frame)
        @php($scale = min(1, 980 / max(1, $frame['width'])))
        <figure style="margin:0">
            <div style="width:{{ round($frame['width'] * $scale) }}px;height:{{ round($frame['height'] * $scale) }}px;overflow:hidden;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.15)">
                <iframe src="{{ $frame['url'] }}" width="{{ $frame['width'] }}" height="{{ $frame['height'] }}" scrolling="no" title="{{ $frame['size'] }}" style="border:0;transform:scale({{ $scale }});transform-origin:top left"></iframe>
            </div>
            <figcaption style="margin-top:6px;font-size:12px;opacity:.7">{{ str_replace('x', '×', $frame['size']) }}</figcaption>
        </figure>
    @endforeach
</div>
