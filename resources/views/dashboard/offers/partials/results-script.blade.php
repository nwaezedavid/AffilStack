{{-- Behaviour for the research form + results block (Get Started and the
     product hub): background submit, progress polling with in-place swap,
     retry without reload, and the Google Maps "Search in" city switcher. --}}
<x-ui.copy-script />
<script>
(function () {
    const csrf = @json(csrf_token());
    let pollTimer = null;

    function mapsUrl(keyword, city) {
        const query = city ? keyword + ' in ' + city : keyword;
        return 'https://www.google.com/maps/search/' + encodeURIComponent(query).replace(/%20/g, '+');
    }

    function bindMaps(scope) {
        scope.querySelectorAll('[data-maps-block]').forEach(function (block) {
            block.querySelectorAll('[data-maps-location]').forEach(function (button) {
                button.addEventListener('click', function () {
                    block.querySelectorAll('[data-maps-location]').forEach(function (other) {
                        other.setAttribute('aria-checked', other === button ? 'true' : 'false');
                    });
                    const city = button.getAttribute('data-maps-location');
                    block.querySelectorAll('[data-maps-keyword]').forEach(function (link) {
                        link.href = mapsUrl(link.getAttribute('data-maps-keyword'), city);
                    });
                });
            });
        });
    }

    function bindRetry(scope) {
        scope.querySelectorAll('form[data-research-retry]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                const button = form.querySelector('button');
                if (button) { button.disabled = true; }
                fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                    body: new FormData(form),
                }).then(function (response) {
                    return response.json().then(function (data) { return { ok: response.ok, data: data }; });
                }).then(function (result) {
                    if (!result.ok) {
                        alert(result.data.message || 'Could not restart the research.');
                        if (button) { button.disabled = false; }
                        return;
                    }
                    refresh(current() ? current().dataset.statusUrl : result.data.status_url, true);
                }).catch(function () { form.submit(); });
            });
        });
    }

    function current() {
        return document.querySelector('[data-research-results]');
    }

    function swap(html) {
        const existing = current();
        const holder = document.createElement('div');
        holder.innerHTML = html.trim();
        const fresh = holder.firstElementChild;
        if (existing && fresh) {
            existing.replaceWith(fresh);
        } else if (fresh) {
            document.querySelector('[data-results-slot]').replaceChildren(fresh);
        }
        init(fresh);
        return fresh;
    }

    function refresh(url, force) {
        clearTimeout(pollTimer);
        fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (!data) {
                    pollTimer = setTimeout(function () { refresh(url, force); }, 8000);
                    return;
                }
                const before = current() ? current().dataset.status : null;
                if (force || before !== data.status || !current()) {
                    const fresh = swap(data.html);
                    if (data.status === 'ready' && fresh && before === 'queued') {
                        fresh.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
                if (data.status === 'queued') {
                    pollTimer = setTimeout(function () { refresh(url, false); }, 4000);
                }
            })
            .catch(function () {
                pollTimer = setTimeout(function () { refresh(url, force); }, 8000);
            });
    }

    function init(scope) {
        if (!scope) {
            return;
        }
        bindMaps(scope);
        bindRetry(scope);
        if (scope.matches && scope.matches('[data-research-results]') && scope.dataset.status === 'queued') {
            clearTimeout(pollTimer);
            pollTimer = setTimeout(function () { refresh(scope.dataset.statusUrl, false); }, 4000);
        }
    }

    // Background submit of the Get Started form.
    document.querySelectorAll('form[data-research-form]').forEach(function (form) {
        const errorsBox = form.querySelector('[data-form-errors]');
        const submit = form.querySelector('[data-research-submit]');
        const submitLabel = form.querySelector('[data-research-submit-label]');

        form.addEventListener('submit', function (event) {
            if (!document.querySelector('[data-results-slot]')) {
                return;
            }
            event.preventDefault();
            errorsBox.hidden = true;
            submit.disabled = true;
            submitLabel.textContent = 'Starting research…';

            fetch(form.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: new FormData(form),
            }).then(function (response) {
                return response.json().then(function (data) { return { status: response.status, data: data }; });
            }).then(function (result) {
                submit.disabled = false;
                submitLabel.textContent = 'Research with AI';
                if (result.status === 201) {
                    form.reset();
                    form.querySelectorAll('[data-network-select]').forEach(function (select) { select.dispatchEvent(new Event('change')); });
                    document.querySelector('[data-results-slot]').scrollIntoView({ behavior: 'smooth', block: 'start' });
                    refresh(result.data.status_url, true);
                    return;
                }
                const messages = result.data.errors
                    ? Object.values(result.data.errors).flat()
                    : [result.data.message || 'Something went wrong — please try again.'];
                errorsBox.replaceChildren();
                const list = document.createElement('ul');
                list.className = 'list-disc list-inside space-y-0.5';
                messages.forEach(function (message) {
                    const li = document.createElement('li');
                    li.textContent = message;
                    list.appendChild(li);
                });
                errorsBox.appendChild(list);
                errorsBox.hidden = false;
            }).catch(function () {
                submit.disabled = false;
                submitLabel.textContent = 'Research with AI';
                form.submit();
            });
        });
    });

    init(current());
})();
</script>
