{{-- The welcome tour: a skippable, resumable spotlight walkthrough of the
     dashboard. Pure JS/CSS, no dependency. Starts by itself on the user's
     home page (Get Started, or a team seat's product page) until they
     finish or skip it once (users.onboarding_*), and on demand via
     "Take the tour" (route onboarding.restart → ?tour=1). Steps whose
     element isn't on the current page are skipped, so it adapts to what
     each kind of account can actually see. --}}
@auth
    @php
        $tourUser = auth()->user();
        $tourHome = $tourUser->isSeat() ? request()->routeIs('offers.show', 'offers.index') : request()->routeIs('dashboard');
        $tourAuto = ! $tourUser->isAffiliateOnly() && $tourHome && ($tourUser->shouldSeeOnboardingTour() || request()->boolean('tour'));
    @endphp
    @if (! $tourUser->isAffiliateOnly())
        <script>
            window.AffilStackTour = {
                autoStart: @json($tourAuto),
                onHome: @json($tourHome),
                isSeat: @json($tourUser->isSeat()),
                userId: @json($tourUser->id),
                completeUrl: @json(route('onboarding.complete')),
                dismissUrl: @json(route('onboarding.dismiss')),
                csrf: @json(csrf_token()),
            };
        </script>
        <script>
            (function () {
                const config = window.AffilStackTour;
                const nav = 'nav[aria-label="Dashboard"]';
                const allSteps = [
                    { title: 'Welcome to AffilStack', body: 'This 1-minute tour shows you how to go from an affiliate link to real buyers. You can skip it any time with Esc — and replay it from "Take the tour".' },
                    { target: nav, title: 'Your menu', body: 'Everything is grouped by what you\'re trying to do: Start here, Find & close buyers, Publish & track, Connections and Account.' },
                    { target: '[data-tour="research-form"]', title: '1. Research a product', body: 'Enter the product name, its official link, your affiliate link and the platform you joined. Click "Research with AI" and our AI does the homework.' },
                    { target: '[data-tour="research-results"]', title: '2. Your results appear here', body: 'What the product does, who needs it, buyer personas, where to find them — plus Google Maps and LinkedIn keywords you can copy or open with one click.' },
                    { target: nav + ' a[href$="/extension"]', title: '3. Install the Chrome extension', body: 'Search a keyword on Google Maps or LinkedIn and click "Add to CRM" on each business or person. On LinkedIn it also writes your messages and replies.' },
                    { target: nav + ' a[href$="/crm"]', title: '4. CRM & Campaigns', body: 'Every prospect you save lands in that product\'s list. Click "Create Campaign" and the AI writes and sends an email to the whole list.' },
                    { target: '[data-tour="feature-tiles"]', title: '5. Create content in one click', body: 'UGC videos, YouTube, blog articles, LinkedIn, X, TikTok, Pinterest and more — each tool opens its own page, already set up for your product.' },
                    { target: nav + ' a[href*="/calendar"]', seatOnly: true, title: 'Plan your posts', body: 'Everything you create can be scheduled on the content calendar.' },
                    { target: '[data-tour="account-stats"]', title: 'Credits & plan', body: 'Each AI action uses credits. Your balance and plan are always here — top up or upgrade from Billing & Plan whenever you need more.' },
                    { target: nav + ' a[href$="/support"]', title: 'We\'re here to help', body: 'Stuck? Open Support to chat with our AI assistant or raise a ticket with the team.' },
                    { title: 'You\'re all set', body: 'Start by researching your first product above. You can replay this tour any time from the "Take the tour" button.' },
                ];
                const steps = allSteps.filter(function (step) { return !step.seatOnly || config.isSeat; });
                const storageKey = 'affilstack-tour-' + config.userId;
                let index = 0;
                let overlay, spotlight, card, lastFocus;

                function store(value) {
                    try { value === null ? localStorage.removeItem(storageKey) : localStorage.setItem(storageKey, JSON.stringify(value)); } catch (e) {}
                }
                function stored() {
                    try { return JSON.parse(localStorage.getItem(storageKey) || 'null'); } catch (e) { return null; }
                }

                function targetFor(step) {
                    if (!step.target) {
                        return null;
                    }
                    return document.querySelector(step.target);
                }

                function isOnScreen(element) {
                    const rect = element.getBoundingClientRect();
                    return rect.width > 0 && rect.height > 0 && rect.right > 0 && rect.left < window.innerWidth;
                }

                function usable(step) {
                    return !step.target || !!targetFor(step);
                }

                function build() {
                    overlay = document.createElement('div');
                    overlay.className = 'fixed inset-0 z-[65]';
                    overlay.addEventListener('click', function (event) { event.preventDefault(); });

                    spotlight = document.createElement('div');
                    spotlight.className = 'tour-spotlight';

                    card = document.createElement('div');
                    card.setAttribute('role', 'dialog');
                    card.setAttribute('aria-modal', 'true');
                    card.setAttribute('aria-labelledby', 'tour-title');
                    card.setAttribute('aria-describedby', 'tour-body');
                    card.className = 'fixed z-[80] w-[min(24rem,calc(100vw-2rem))] rounded-3xl bg-white p-5 shadow-2xl ring-1 ring-black/5';
                    card.innerHTML =
                        '<div class="flex items-center justify-between gap-3">' +
                            '<span data-tour-progress class="text-[11px] font-mono uppercase tracking-[0.16em] text-gold-600"></span>' +
                            '<button type="button" data-tour-skip class="rounded-full px-2 py-1 text-xs font-medium text-ink-400 hover:bg-surface-muted hover:text-ink-900">Skip tour</button>' +
                        '</div>' +
                        '<h2 id="tour-title" class="mt-2 font-display text-lg font-semibold text-navy-900"></h2>' +
                        '<p id="tour-body" class="mt-1 text-sm leading-relaxed text-ink-600"></p>' +
                        '<p data-tour-hint hidden class="mt-2 rounded-xl bg-brand-50 px-3 py-2 text-xs text-brand-700">Tip: open the menu (☰, top left) to see this.</p>' +
                        '<div class="mt-3 flex gap-1" data-tour-dots></div>' +
                        '<div class="mt-4 flex items-center justify-between gap-2">' +
                            '<button type="button" data-tour-back class="rounded-full px-3 py-2 text-sm font-medium text-ink-600 hover:bg-surface-muted disabled:invisible">Back</button>' +
                            '<button type="button" data-tour-next class="inline-flex items-center gap-1.5 rounded-full bg-navy-900 px-5 py-2 text-sm font-semibold text-white hover:bg-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-brand-500"></button>' +
                        '</div>';

                    document.body.append(overlay, spotlight, card);
                    card.querySelector('[data-tour-next]').addEventListener('click', next);
                    card.querySelector('[data-tour-back]').addEventListener('click', back);
                    card.querySelector('[data-tour-skip]').addEventListener('click', function () { finish('dismiss'); });
                    document.addEventListener('keydown', onKey, true);
                    window.addEventListener('resize', place);
                    window.addEventListener('scroll', place, true);
                }

                function onKey(event) {
                    if (!card) {
                        return;
                    }
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        finish('dismiss');
                    } else if (event.key === 'ArrowRight') {
                        event.preventDefault();
                        next();
                    } else if (event.key === 'ArrowLeft') {
                        event.preventDefault();
                        back();
                    } else if (event.key === 'Tab') {
                        const focusable = Array.from(card.querySelectorAll('button:not([disabled])'));
                        const first = focusable[0];
                        const last = focusable[focusable.length - 1];
                        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
                        else if (!card.contains(document.activeElement)) { event.preventDefault(); first.focus(); }
                    }
                }

                function place() {
                    if (!card) {
                        return;
                    }
                    const step = steps[index];
                    const target = targetFor(step);
                    const visible = target && isOnScreen(target);
                    card.querySelector('[data-tour-hint]').hidden = !(target && !visible);
                    const mobile = window.innerWidth < 640;

                    overlay.style.background = visible ? 'transparent' : 'rgba(10, 23, 48, 0.62)';
                    if (!visible) {
                        spotlight.style.cssText = 'display:none;';
                        if (mobile) {
                            card.style.cssText = 'left:1rem;right:1rem;bottom:1rem;top:auto;width:auto;';
                        } else {
                            card.style.cssText = 'left:50%;top:50%;transform:translate(-50%,-50%);';
                        }
                        return;
                    }

                    const rect = target.getBoundingClientRect();
                    const pad = 8;
                    const top = Math.max(rect.top - pad, 4);
                    const height = Math.min(rect.height + pad * 2, window.innerHeight - top - 4);
                    spotlight.style.cssText = 'top:' + top + 'px;left:' + (rect.left - pad) + 'px;width:' + (rect.width + pad * 2) + 'px;height:' + height + 'px;';

                    if (mobile) {
                        const roomBelow = window.innerHeight - (top + height);
                        card.style.cssText = roomBelow > 220
                            ? 'left:1rem;right:1rem;width:auto;top:' + (top + height + 12) + 'px;'
                            : 'left:1rem;right:1rem;width:auto;bottom:1rem;top:auto;';
                        return;
                    }

                    const cardWidth = card.offsetWidth;
                    const cardHeight = card.offsetHeight;
                    let left;
                    let cardTop;
                    if (rect.right + cardWidth + 24 < window.innerWidth && rect.width < window.innerWidth / 2) {
                        left = rect.right + 16;
                        cardTop = Math.min(Math.max(rect.top, 16), window.innerHeight - cardHeight - 16);
                    } else if (window.innerHeight - (top + height) > cardHeight + 24) {
                        left = Math.min(Math.max(rect.left, 16), window.innerWidth - cardWidth - 16);
                        cardTop = top + height + 12;
                    } else if (top > cardHeight + 24) {
                        left = Math.min(Math.max(rect.left, 16), window.innerWidth - cardWidth - 16);
                        cardTop = top - cardHeight - 12;
                    } else {
                        left = window.innerWidth - cardWidth - 24;
                        cardTop = window.innerHeight - cardHeight - 24;
                    }
                    card.style.cssText = 'left:' + left + 'px;top:' + cardTop + 'px;';
                }

                function show(newIndex, direction) {
                    while (newIndex >= 0 && newIndex < steps.length && !usable(steps[newIndex])) {
                        newIndex += direction;
                    }
                    if (newIndex >= steps.length) {
                        finish('complete');
                        return;
                    }
                    index = Math.max(0, newIndex);
                    store({ step: index, path: window.location.pathname });

                    const step = steps[index];
                    const usableSteps = steps.filter(usable);
                    const position = usableSteps.indexOf(step);
                    card.querySelector('[data-tour-progress]').textContent = 'Step ' + (position + 1) + ' of ' + usableSteps.length;
                    card.querySelector('#tour-title').textContent = step.title;
                    card.querySelector('#tour-body').textContent = step.body;
                    const dots = card.querySelector('[data-tour-dots]');
                    dots.replaceChildren();
                    usableSteps.forEach(function (_, i) {
                        const dot = document.createElement('span');
                        dot.className = 'h-1.5 flex-1 rounded-full ' + (i <= position ? 'bg-gold-400' : 'bg-surface-muted');
                        dots.appendChild(dot);
                    });
                    const backButton = card.querySelector('[data-tour-back]');
                    backButton.disabled = position <= 0;
                    const nextButton = card.querySelector('[data-tour-next]');
                    nextButton.textContent = position === usableSteps.length - 1 ? 'Finish' : (position === 0 ? 'Show me around' : 'Next');

                    const target = targetFor(step);
                    if (target && isOnScreen(target)) {
                        target.scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
                        setTimeout(place, 350);
                    }
                    place();
                    nextButton.focus({ preventScroll: true });
                }

                function next() { show(index + 1, 1); }
                function back() { show(index - 1, -1); }

                function finish(kind) {
                    if (!card) {
                        return;
                    }
                    fetch(kind === 'complete' ? config.completeUrl : config.dismissUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': config.csrf, Accept: 'application/json' },
                    }).catch(function () {});
                    store(null);
                    document.removeEventListener('keydown', onKey, true);
                    window.removeEventListener('resize', place);
                    window.removeEventListener('scroll', place, true);
                    overlay.remove();
                    spotlight.remove();
                    card.remove();
                    card = null;
                    config.autoStart = false;
                    if (lastFocus && lastFocus.focus) {
                        lastFocus.focus();
                    }
                }

                function start(fromIndex) {
                    if (card) {
                        return;
                    }
                    lastFocus = document.activeElement;
                    build();
                    show(fromIndex || 0, 1);
                }

                window.AffilStackTour.start = start;

                // "Take the tour" links start it right here when JS is on;
                // the link itself (onboarding.restart) is the no-JS fallback.
                document.addEventListener('click', function (event) {
                    const link = event.target.closest('[data-tour-restart]');
                    if (!link || !config.onHome) {
                        return;
                    }
                    event.preventDefault();
                    store(null);
                    start(0);
                });

                document.addEventListener('DOMContentLoaded', function () {
                    if (!config.autoStart) {
                        return;
                    }
                    const resume = stored();
                    const fromIndex = resume && resume.path === window.location.pathname && !new URLSearchParams(window.location.search).has('tour') ? resume.step : 0;
                    setTimeout(function () { start(fromIndex); }, 400);
                });
            })();
        </script>
    @endif
@endauth
