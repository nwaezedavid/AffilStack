@props(['samples', 'languages' => \App\Services\ApiDocs\CodeSampleGenerator::LANGUAGES])

{{-- Language switcher over a set of code samples. Picking a language
     switches every code-tabs block on the page (remembered per browser). --}}
<div {{ $attributes->merge(['class' => 'space-y-2']) }} data-code-tabs>
    <div class="flex gap-1 overflow-x-auto pb-1" role="tablist" aria-label="Language">
        @foreach ($languages as $key => $label)
            @continue(! isset($samples[$key]))
            <button type="button" role="tab" data-lang-tab="{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                class="shrink-0 rounded-full px-3 py-1 text-xs font-medium transition ring-1 ring-inset ring-line text-ink-600 hover:text-navy-900 aria-selected:bg-navy-900 aria-selected:text-white aria-selected:ring-navy-900">
                {{ $label }}
            </button>
        @endforeach
    </div>
    @foreach ($languages as $key => $label)
        @continue(! isset($samples[$key]))
        <div data-lang-panel="{{ $key }}" @class(['hidden' => ! $loop->first]) role="tabpanel">
            <x-ui.code-block :code="$samples[$key]" :label="$label" />
        </div>
    @endforeach
</div>

@once
    @push('scripts')
        <script>
            (function () {
                function selectLanguage(lang) {
                    document.querySelectorAll('[data-code-tabs]').forEach(function (group) {
                        if (!group.querySelector('[data-lang-panel="' + lang + '"]')) {
                            return;
                        }
                        group.querySelectorAll('[data-lang-tab]').forEach(function (tab) {
                            tab.setAttribute('aria-selected', tab.dataset.langTab === lang ? 'true' : 'false');
                        });
                        group.querySelectorAll('[data-lang-panel]').forEach(function (panel) {
                            panel.classList.toggle('hidden', panel.dataset.langPanel !== lang);
                        });
                    });
                }
                document.addEventListener('click', function (event) {
                    const tab = event.target.closest('[data-lang-tab]');
                    if (!tab) {
                        return;
                    }
                    selectLanguage(tab.dataset.langTab);
                    try { localStorage.setItem('affilstack.codeLang', tab.dataset.langTab); } catch (e) {}
                });
                try {
                    const saved = localStorage.getItem('affilstack.codeLang');
                    if (saved) { selectLanguage(saved); }
                } catch (e) {}
            })();
        </script>
    @endpush
@endonce
