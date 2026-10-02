@props(['target' => null, 'text' => null, 'label' => 'Copy', 'copied' => 'Copied!', 'variant' => 'secondary', 'size' => 'sm'])

{{-- Copies either a literal string (text) or the value/text of the element
     with id="target", then flips its label to "Copied!" for two seconds.
     Works on http:// too (falls back to a hidden textarea). --}}
@php
    $variants = [
        'secondary' => 'bg-white text-navy-900 ring-1 ring-line hover:bg-surface-muted',
        'primary' => 'bg-navy-900 text-white hover:bg-navy-800 shadow-sm',
        'gold' => 'bg-gold-400 text-navy-950 hover:bg-gold-300 shadow-sm',
        'light' => 'bg-white/15 text-white ring-1 ring-white/25 hover:bg-white/25',
    ];
    $sizes = ['xs' => 'px-2.5 py-1 text-[11px]', 'sm' => 'px-3 py-1.5 text-xs', 'md' => 'px-4 py-2 text-sm'];
@endphp
<button type="button"
        data-copy-button
        @if ($target) data-copy-target="{{ $target }}" @endif
        @if ($text !== null) data-copy-text="{{ $text }}" @endif
        data-copied-label="{{ $copied }}"
        {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-1.5 rounded-full font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-brand-500 '.($variants[$variant] ?? $variants['secondary']).' '.($sizes[$size] ?? $sizes['sm'])]) }}>
    <x-heroicon-m-clipboard-document class="h-4 w-4 shrink-0" data-copy-icon />
    <x-heroicon-m-check class="h-4 w-4 shrink-0 hidden text-emerald-500" data-copied-icon />
    <span data-copy-label>{{ $label }}</span>
</button>

@once
    @push('scripts')
        <script>
            (function () {
                function copyText(text) {
                    if (navigator.clipboard && window.isSecureContext) {
                        return navigator.clipboard.writeText(text);
                    }
                    return new Promise(function (resolve, reject) {
                        var area = document.createElement('textarea');
                        area.value = text;
                        area.setAttribute('readonly', '');
                        area.style.position = 'fixed';
                        area.style.opacity = '0';
                        document.body.appendChild(area);
                        area.select();
                        try { document.execCommand('copy') ? resolve() : reject(); } catch (e) { reject(e); }
                        document.body.removeChild(area);
                    });
                }

                window.affilstackCopy = copyText;

                document.addEventListener('click', function (event) {
                    var button = event.target.closest('[data-copy-button]');
                    if (!button) { return; }
                    var text = button.getAttribute('data-copy-text');
                    if (text === null) {
                        var target = document.getElementById(button.getAttribute('data-copy-target'));
                        if (!target) { return; }
                        text = 'value' in target && target.tagName !== 'DIV' && target.tagName !== 'PRE' ? target.value : target.innerText;
                    }
                    copyText(text).then(function () {
                        var label = button.querySelector('[data-copy-label]');
                        var original = label.getAttribute('data-original') || label.textContent;
                        label.setAttribute('data-original', original);
                        label.textContent = button.getAttribute('data-copied-label');
                        button.querySelector('[data-copy-icon]').classList.add('hidden');
                        button.querySelector('[data-copied-icon]').classList.remove('hidden');
                        clearTimeout(button._copyTimer);
                        button._copyTimer = setTimeout(function () {
                            label.textContent = original;
                            button.querySelector('[data-copy-icon]').classList.remove('hidden');
                            button.querySelector('[data-copied-icon]').classList.add('hidden');
                        }, 2000);
                    });
                });
            })();
        </script>
    @endpush
@endonce
