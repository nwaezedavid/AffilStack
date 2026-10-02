/* AffilStack affiliate banner loader. Usage:
   <script async src=".../embed/banner.js" data-ref="CODE" data-size="300x250" data-design="rotate"></script> */
(function () {
    var endpoint = {!! json_encode($endpoint, JSON_UNESCAPED_SLASHES) !!};
    var sizePattern = /^\d{2,4}x\d{2,4}$/;

    function mount(script) {
        if (!script || script.getAttribute('data-affilstack-mounted')) { return; }
        script.setAttribute('data-affilstack-mounted', '1');

        var size = script.getAttribute('data-size') || '300x250';
        if (!sizePattern.test(size)) { return; }
        var parts = size.split('x');
        var width = parseInt(parts[0], 10);
        var height = parseInt(parts[1], 10);
        var params = 'ref=' + encodeURIComponent(script.getAttribute('data-ref') || '') +
            '&design=' + encodeURIComponent(script.getAttribute('data-design') || 'rotate');

        var frame = document.createElement('iframe');
        frame.src = endpoint + '/' + size + '?' + params;
        frame.width = String(width);
        frame.height = String(height);
        frame.title = 'AffilStack';
        frame.loading = 'lazy';
        frame.scrolling = 'no';
        frame.setAttribute('sandbox', 'allow-popups allow-popups-to-escape-sandbox');
        frame.style.cssText = 'border:0;display:block;max-width:100%;height:auto;aspect-ratio:' + width + '/' + height + ';overflow:hidden';
        script.parentNode.insertBefore(frame, script.nextSibling);
    }

    mount(document.currentScript);

    var scripts = document.querySelectorAll('script[data-ref][src*="/embed/banner.js"]');
    for (var i = 0; i < scripts.length; i++) { mount(scripts[i]); }
})();
