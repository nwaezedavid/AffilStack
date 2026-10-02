<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>AffilStack</title>
    <style>
        html, body { margin: 0; padding: 0; background: transparent; overflow: hidden; height: 100%; }
        a { display: block; width: 100%; height: 100%; line-height: 0; outline-offset: -3px; }
        img { display: block; width: 100%; height: 100%; object-fit: contain; border: 0; }
    </style>
</head>
<body>
    <a href="{{ $link }}" target="_blank" rel="sponsored noopener">
        <img src="{{ $banner['kind'] === 'upload' ? $banner['image_url'] : $banner['svg_url'] }}" width="{{ $banner['width'] }}" height="{{ $banner['height'] }}" alt="{{ $banner['alt'] }}">
    </a>
</body>
</html>
