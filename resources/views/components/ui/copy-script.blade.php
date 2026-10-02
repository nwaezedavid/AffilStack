{{-- Click handler behind every <x-ui.copy-button>. Safe to include more than
     once (it binds a single delegated listener), so pages whose buttons
     arrive later via polling can include it up front. --}}
<script>
(function () {
    if (window.__affilstackCopy) {
        return;
    }
    window.__affilstackCopy = true;

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve) {
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            try { document.execCommand('copy'); } catch (e) {}
            area.remove();
            resolve();
        });
    }

    window.affilstackCopy = copyText;

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-copy], [data-copy-target]');
        if (!button) {
            return;
        }
        let text = button.getAttribute('data-copy');
        if (text === null) {
            const target = document.getElementById(button.getAttribute('data-copy-target'));
            if (!target) {
                return;
            }
            text = ('value' in target && target.tagName !== 'DIV' && target.tagName !== 'PRE') ? target.value : target.innerText;
        }
        copyText(text || '').then(function () {
            const label = button.querySelector('[data-copy-text]');
            const icon = button.querySelector('[data-copy-icon]');
            const tick = button.querySelector('[data-copied-icon]');
            if (label) {
                label.textContent = button.getAttribute('data-copied-label') || 'Copied!';
            }
            if (icon && tick) {
                icon.classList.add('hidden');
                tick.classList.remove('hidden');
            }
            clearTimeout(button._copyTimer);
            button._copyTimer = setTimeout(function () {
                if (label) {
                    label.textContent = button.getAttribute('data-copy-label') || 'Copy';
                }
                if (icon && tick) {
                    icon.classList.remove('hidden');
                    tick.classList.add('hidden');
                }
            }, 2000);
        }, function () {});
    });
})();
</script>
