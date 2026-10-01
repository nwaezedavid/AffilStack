{{-- The "Affiliate platform" picker. Works as a plain grouped <select>
     without JavaScript; with it, the select is replaced by a searchable,
     keyboard-friendly list (type to filter — aliases like "ShareASale"
     find their current home). Choosing "Other" reveals a free-text field.
     A previously stored name that isn't in the list shows as "Other". --}}
@php
    $groups = config('affiliate_networks.groups');
    $otherValue = config('affiliate_networks.other_value');
    $known = collect($groups)->flatMap(fn ($group) => collect($group['networks'])->pluck('name'))->all();
    $current = (string) old('affiliate_network', $value ?? '');
    $otherText = (string) old('affiliate_network_other', '');
    if ($current !== '' && $current !== $otherValue && ! in_array($current, $known, true)) {
        $otherText = $current;
        $current = $otherValue;
    }
@endphp
<div data-network-picker class="relative">
    <noscript><style>[data-network-other] { display: block !important; }</style></noscript>
    <label for="affiliate_network" class="block text-sm font-medium text-navy-900 mb-1.5">Affiliate platform</label>

    <select id="affiliate_network" name="affiliate_network" required data-network-select
            class="w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        <option value="">Choose where you got your affiliate link…</option>
        @foreach ($groups as $group)
            <optgroup label="{{ $group['label'] }}">
                @foreach ($group['networks'] as $network)
                    <option value="{{ $network['name'] }}" data-aliases="{{ implode(' ', $network['aliases']) }}" @selected($current === $network['name'])>{{ $network['name'] }}</option>
                @endforeach
            </optgroup>
        @endforeach
        <option value="{{ $otherValue }}" @selected($current === $otherValue)>Other (type the name)</option>
    </select>

    {{-- JS-enhanced trigger + panel (hidden until the script takes over). --}}
    <button type="button" data-network-trigger hidden
            role="combobox" aria-haspopup="listbox" aria-expanded="false" aria-controls="network-listbox" aria-labelledby="network-trigger-label"
            class="flex w-full items-center justify-between gap-2 rounded-xl border border-line bg-white px-3.5 py-2.5 text-left text-sm shadow-[0_1px_2px_rgba(16,24,40,0.04)] hover:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500">
        <span class="flex items-center gap-2 min-w-0">
            <x-heroicon-o-building-storefront class="h-4 w-4 shrink-0 text-brand-600" aria-hidden="true" />
            <span id="network-trigger-label" data-network-label class="truncate text-ink-400">Choose where you got your affiliate link…</span>
        </span>
        <x-heroicon-m-chevron-up-down class="h-4 w-4 shrink-0 text-ink-400" aria-hidden="true" />
    </button>

    <div data-network-panel hidden
         class="absolute left-0 right-0 z-30 mt-2 overflow-hidden rounded-2xl border border-line bg-white shadow-xl ring-1 ring-black/5">
        <div class="border-b border-line p-2.5">
            <label for="network-search" class="sr-only">Search affiliate platforms</label>
            <div class="relative">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" aria-hidden="true" />
                <input id="network-search" type="search" data-network-search autocomplete="off"
                       placeholder="Search 75+ platforms — e.g. Impact, Selar, ClickBank"
                       aria-controls="network-listbox" aria-autocomplete="list"
                       class="w-full rounded-lg border border-line bg-surface-muted py-2 pl-9 pr-3 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
        </div>
        <ul id="network-listbox" role="listbox" aria-label="Affiliate platforms" data-network-list class="max-h-72 overflow-y-auto py-1.5 text-sm"></ul>
        <p data-network-empty hidden class="px-4 py-6 text-center text-sm text-ink-600">No match — choose <strong>Other</strong> and type the name.</p>
    </div>

    <div data-network-other @class(['mt-3', 'hidden' => $current !== $otherValue])>
        <label for="affiliate_network_other" class="block text-sm font-medium text-navy-900 mb-1.5">Platform name</label>
        <input id="affiliate_network_other" name="affiliate_network_other" value="{{ $otherText }}" maxlength="255"
               placeholder="e.g. the brand's own partner programme"
               class="w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                document.querySelectorAll('[data-network-picker]').forEach(function (root) {
                    const select = root.querySelector('[data-network-select]');
                    const trigger = root.querySelector('[data-network-trigger]');
                    const label = root.querySelector('[data-network-label]');
                    const panel = root.querySelector('[data-network-panel]');
                    const search = root.querySelector('[data-network-search]');
                    const list = root.querySelector('[data-network-list]');
                    const empty = root.querySelector('[data-network-empty]');
                    const otherWrap = root.querySelector('[data-network-other]');
                    const otherInput = otherWrap.querySelector('input');
                    const otherValue = @json($otherValue);
                    let active = -1;

                    // Build the listbox from the native select so both always agree.
                    const items = [];
                    let optionIndex = 0;
                    Array.from(select.children).forEach(function (node) {
                        const options = node.tagName === 'OPTGROUP' ? Array.from(node.children) : [node];
                        if (node.tagName === 'OPTGROUP') {
                            const heading = document.createElement('li');
                            heading.setAttribute('role', 'presentation');
                            heading.className = 'px-4 pt-3 pb-1 text-[10px] font-mono uppercase tracking-[0.16em] text-gold-600';
                            heading.textContent = node.label;
                            heading.dataset.group = node.label;
                            list.appendChild(heading);
                        }
                        options.forEach(function (option) {
                            if (!option.value) {
                                return;
                            }
                            const li = document.createElement('li');
                            li.id = 'network-option-' + (optionIndex++);
                            li.setAttribute('role', 'option');
                            li.dataset.value = option.value;
                            li.dataset.group = node.tagName === 'OPTGROUP' ? node.label : '';
                            li.dataset.search = (option.textContent + ' ' + (option.dataset.aliases || '')).toLowerCase();
                            li.className = 'mx-1.5 flex cursor-pointer items-center justify-between gap-2 rounded-lg px-3 py-2 text-ink-900 hover:bg-brand-50';
                            li.textContent = option.textContent;
                            if (option.value === otherValue) {
                                li.className += ' mt-1 border-t border-line font-medium text-brand-600';
                            }
                            li.addEventListener('mousedown', function (event) { event.preventDefault(); });
                            li.addEventListener('click', function () { choose(option.value); });
                            list.appendChild(li);
                            items.push(li);
                        });
                    });

                    function visibleItems() {
                        return items.filter(function (li) { return !li.hidden; });
                    }

                    function highlight(index) {
                        const visible = visibleItems();
                        items.forEach(function (li) { li.classList.remove('bg-brand-50', 'ring-1', 'ring-brand-100'); li.setAttribute('aria-selected', 'false'); });
                        if (!visible.length) {
                            active = -1;
                            search.removeAttribute('aria-activedescendant');
                            return;
                        }
                        active = Math.max(0, Math.min(index, visible.length - 1));
                        const li = visible[active];
                        li.classList.add('bg-brand-50', 'ring-1', 'ring-brand-100');
                        li.setAttribute('aria-selected', 'true');
                        search.setAttribute('aria-activedescendant', li.id);
                        li.scrollIntoView({ block: 'nearest' });
                    }

                    function filter() {
                        const term = search.value.trim().toLowerCase();
                        const groupsWithHits = new Set();
                        items.forEach(function (li) {
                            const hit = !term || li.dataset.search.indexOf(term) !== -1 || li.dataset.value === otherValue;
                            li.hidden = !hit;
                            if (hit && li.dataset.group) {
                                groupsWithHits.add(li.dataset.group);
                            }
                        });
                        list.querySelectorAll('li[role="presentation"]').forEach(function (heading) {
                            heading.hidden = !groupsWithHits.has(heading.dataset.group);
                        });
                        empty.hidden = visibleItems().some(function (li) { return li.dataset.value !== otherValue; });
                        highlight(0);
                    }

                    function sync() {
                        const option = select.options[select.selectedIndex];
                        const chosen = option && option.value;
                        label.textContent = chosen ? option.textContent : 'Choose where you got your affiliate link…';
                        label.classList.toggle('text-ink-400', !chosen);
                        label.classList.toggle('text-ink-900', !!chosen);
                        const isOther = select.value === otherValue;
                        otherWrap.classList.toggle('hidden', !isOther);
                        otherInput.required = isOther;
                    }

                    function open() {
                        panel.hidden = false;
                        trigger.setAttribute('aria-expanded', 'true');
                        search.value = '';
                        filter();
                        search.focus();
                    }

                    function close(focusTrigger) {
                        panel.hidden = true;
                        trigger.setAttribute('aria-expanded', 'false');
                        if (focusTrigger) {
                            trigger.focus();
                        }
                    }

                    function choose(value) {
                        select.value = value;
                        select.dispatchEvent(new Event('change'));
                        close(true);
                        if (value === otherValue) {
                            otherInput.focus();
                        }
                    }

                    select.classList.add('sr-only');
                    select.setAttribute('tabindex', '-1');
                    select.setAttribute('aria-hidden', 'true');
                    trigger.hidden = false;
                    root.querySelector('label[for="affiliate_network"]').id = 'network-label';
                    trigger.setAttribute('aria-labelledby', 'network-label network-trigger-label');
                    root.querySelector('label[for="affiliate_network"]').addEventListener('click', function (event) { event.preventDefault(); trigger.focus(); });

                    select.addEventListener('change', sync);
                    select.addEventListener('invalid', function () { trigger.classList.add('ring-2', 'ring-red-400'); });
                    trigger.addEventListener('click', function () { panel.hidden ? open() : close(true); });
                    trigger.addEventListener('keydown', function (event) {
                        if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            open();
                        }
                    });
                    search.addEventListener('input', filter);
                    search.addEventListener('keydown', function (event) {
                        if (event.key === 'ArrowDown') { event.preventDefault(); highlight(active + 1); }
                        else if (event.key === 'ArrowUp') { event.preventDefault(); highlight(active - 1); }
                        else if (event.key === 'Enter') {
                            event.preventDefault();
                            const li = visibleItems()[active];
                            if (li) { choose(li.dataset.value); }
                        } else if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(true); }
                        else if (event.key === 'Tab') { close(false); }
                    });
                    document.addEventListener('click', function (event) {
                        if (!root.contains(event.target)) { close(false); }
                    });
                    select.addEventListener('change', function () { trigger.classList.remove('ring-2', 'ring-red-400'); });
                    sync();
                });
            })();
        </script>
    @endpush
@endonce
