{{-- Click handler behind every <x-ui.copy-button>. Safe to include more than
     once (it binds a single delegated listener), so pages whose buttons
     arrive later via polling can include it up front. --}}
<script>
(function () {
    if (window.__affilstackCopy) {
        return;
    }
    window.__affilstackCopy = true;

    function fallbackCopy(text) {
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        try { document.execCommand('copy'); } catch (e) {}
        area.remove();
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-copy]');
        if (!button) {
return;
        }
        const text = button.getAttribute('data-copy') || '';
        const done = function () {
const label = button.querySelector('[data-copy-text]');
if (label) {
    label.textContent = 'Copied!';
    setTimeout(function () { label.textContent = button.getAttribute('data-copy-label') || 'Copy'; }, 1600);
}
        };
        if (navigator.clipboard && window.isSecureContext) {
navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); done(); });
        } else {
fallbackCopy(text);
done();
        }
    });
})();
        </script>
