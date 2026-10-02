@props(['code', 'label' => null, 'lang' => null, 'maxHeight' => 'max-h-96'])

{{-- A dark code panel with a one-click copy button. "code" is printed as
     plain text (escaped), so it is safe for any sample or payload. --}}
<div {{ $attributes->merge(['class' => 'group/code relative overflow-hidden rounded-xl bg-navy-950 ring-1 ring-navy-800']) }} data-code-block>
    <div class="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-2">
        <span class="truncate font-mono text-[11px] uppercase tracking-[0.14em] text-white/50">{{ $label ?? $lang ?? 'Code' }}</span>
        <button type="button" class="inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-white/70 transition hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-gold-300" data-copy>
            <span data-copy-label>Copy</span>
        </button>
    </div>
    <pre class="{{ $maxHeight }} overflow-auto px-4 py-3 text-[12.5px] leading-relaxed text-slate-100"><code class="font-mono whitespace-pre" data-copy-source>{{ $code }}</code></pre>
</div>

@once
    @push('scripts')
        <script>
            // Copy buttons for code blocks and any [data-copy-text] element.
            document.addEventListener('click', function (event) {
                const button = event.target.closest('[data-copy], [data-copy-text]');
                if (!button) {
                    return;
                }
                const text = button.hasAttribute('data-copy-text')
                    ? button.getAttribute('data-copy-text')
                    : button.closest('[data-code-block]').querySelector('[data-copy-source]').textContent;
                const label = button.querySelector('[data-copy-label]');
                const done = function () {
                    if (label) {
                        const original = label.textContent;
                        label.textContent = 'Copied';
                        setTimeout(function () { label.textContent = original; }, 1500);
                    }
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(done);
                } else {
                    const area = document.createElement('textarea');
                    area.value = text;
                    area.setAttribute('readonly', '');
                    area.style.position = 'fixed';
                    area.style.opacity = '0';
                    document.body.appendChild(area);
                    area.select();
                    document.execCommand('copy');
                    area.remove();
                    done();
                }
            });
        </script>
    @endpush
@endonce
