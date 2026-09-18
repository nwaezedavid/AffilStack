{{-- GTM's required <body>-start noscript fallback. Expects $gtmPublicId. --}}
@if ($gtmPublicId)
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmPublicId }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
@endif
