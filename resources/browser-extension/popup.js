// AffilStack popup (v1.2).
//
// Opens when the user clicks the toolbar icon. Using activeTab + scripting it
// injects extractor.js into the CURRENT tab only, at that moment, to see
// whether this is a Google Maps business/results page, a LinkedIn profile,
// company page or conversation — and shows the matching tool:
//   Maps      → add the business(es) to a CRM list for a product
//   LinkedIn  → generate a personalised connection note / DM / follow-ups
//   Messaging → suggest the best reply to the prospect
// Nothing is read or sent unless the user opens the popup and clicks.

const $ = (id) => document.getElementById(id);

const state = {
  apiBaseUrl: '',
  siteUrl: '',
  token: '',
  connection: 'checking', // checking | connected | missing | invalid | offline
  me: null,
  tab: null,
  page: null,
  offers: [],
  lists: [],
  canUseMaps: true,
  lastOfferId: null,
  inCrm: {}, // index → match (Maps items already in the CRM)
  channel: 'email',
};

// ------------------------------------------------------------ DOM helpers --

/** Builds an element. Text is always set via textContent (page data is untrusted). */
function h(tag, props = {}, ...children) {
  const el = document.createElement(tag);
  for (const [key, value] of Object.entries(props || {})) {
    if (value === null || value === undefined || value === false) {
      continue;
    }
    if (key === 'class') {
      el.className = value;
    } else if (key === 'text') {
      el.textContent = value;
    } else if (key.startsWith('on') && typeof value === 'function') {
      el.addEventListener(key.slice(2).toLowerCase(), value);
    } else if (key in el && typeof value !== 'string') {
      el[key] = value;
    } else {
      el.setAttribute(key, value === true ? '' : value);
    }
  }
  for (const child of children.flat()) {
    if (child === null || child === undefined || child === false) {
      continue;
    }
    el.appendChild(typeof child === 'string' || typeof child === 'number' ? document.createTextNode(String(child)) : child);
  }
  return el;
}

function icon(name) {
  const paths = {
    map: 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
    link: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
    spark: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
    key: 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
  };
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('width', '24');
  svg.setAttribute('height', '24');
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', '1.6');
  svg.setAttribute('aria-hidden', 'true');
  const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
  path.setAttribute('stroke-linecap', 'round');
  path.setAttribute('stroke-linejoin', 'round');
  path.setAttribute('d', paths[name] || paths.spark);
  svg.appendChild(path);
  return svg;
}

function initials(name) {
  return String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('') || '?';
}

function openUrl(url) {
  if (url) {
    chrome.tabs.create({ url });
  }
}

let toastTimer = null;
function toast(message, { kind = 'success', linkText = null, linkUrl = null, ms = 6000 } = {}) {
  const el = $('toast');
  el.textContent = '';
  el.className = `toast${kind === 'error' ? ' toast-error' : ''}`;
  el.appendChild(h('span', { text: message }));
  if (linkText && linkUrl) {
    el.appendChild(h('a', { href: linkUrl, text: linkText, onclick: (e) => { e.preventDefault(); openUrl(linkUrl); } }));
  }
  el.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.hidden = true; }, ms);
}

function setBusy(button, busy, busyText) {
  if (busy) {
    button.dataset.label = button.textContent;
    button.textContent = busyText;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
  } else {
    button.textContent = button.dataset.label || button.textContent;
    button.disabled = false;
    button.removeAttribute('aria-busy');
  }
}

// ----------------------------------------------------------- Connection --

function renderConnection() {
  const pill = $('connectionPill');
  const pillText = $('connectionPillText');
  const sub = $('connectionText');
  const map = {
    checking: ['pill-checking', 'Checking', 'Checking your connection…'],
    connected: ['pill-connected', 'Connected', state.me ? `Connected as ${state.me.email}` : 'Connected'],
    missing: ['pill-failed', 'Not connected', 'Add your AffilStack token to start'],
    invalid: ['pill-failed', 'Token rejected', 'Reconnect the extension to continue'],
    offline: ['pill-failed', 'Offline', "Can't reach AffilStack right now"],
  };
  const [cls, label, text] = map[state.connection] || map.checking;
  pill.className = `pill ${cls}`;
  pillText.textContent = label;
  sub.textContent = text;
  sub.title = text;
  const credits = $('creditsText');
  const hasCredits = state.connection === 'connected' && state.me && typeof state.me.credits_balance === 'number';
  credits.hidden = !hasCredits;
  credits.textContent = hasCredits ? `${state.me.credits_balance.toLocaleString()} credits left` : '';
}

function connectView() {
  const extensionPage = `${state.siteUrl}/extension`;
  return h('div', { class: 'empty' },
    h('span', { class: 'empty-icon' }, icon('key')),
    h('h2', { text: 'Connect your AffilStack account' }),
    h('p', { text: 'The extension needs a token from your dashboard — it never asks for your password.' }),
    h('ol', { class: 'steps' },
      h('li', { text: 'Open Browser Extension in your AffilStack dashboard.' }),
      h('li', { text: 'Generate a token (one per device).' }),
      h('li', { text: 'Paste it into the extension settings and click Save & verify.' }),
    ),
    h('div', { class: 'actions', style: 'justify-content:center' },
      h('button', { class: 'btn btn-primary', type: 'button', text: 'Add your token', onclick: () => chrome.runtime.openOptionsPage() }),
      h('button', { class: 'btn btn-secondary', type: 'button', text: 'Get a token', onclick: () => openUrl(extensionPage) }),
    ),
  );
}

function connectionProblemView() {
  if (state.connection === 'invalid') {
    return h('div', { class: 'alert alert-error', role: 'alert' },
      h('p', {}, h('strong', { text: 'Your token was rejected. ' }), 'It may have been revoked or copied incompletely.'),
      h('div', { class: 'actions' },
        h('button', { class: 'btn btn-primary btn-sm', type: 'button', text: 'Fix connection', onclick: () => chrome.runtime.openOptionsPage() }),
        h('button', { class: 'btn-link', type: 'button', text: 'Generate a new token', onclick: () => openUrl(`${state.siteUrl}/extension`) }),
      ),
    );
  }
  return h('div', { class: 'alert alert-error', role: 'alert' },
    h('p', { text: "Couldn't reach AffilStack — check your internet connection." }),
    h('div', { class: 'actions' }, h('button', { class: 'btn btn-secondary btn-sm', type: 'button', text: 'Try again', onclick: () => location.reload() })),
  );
}

// ------------------------------------------------------------- Pickers --

function selectedOffer(select) {
  const id = Number(select.value);
  return state.offers.find((offer) => offer.id === id) || null;
}

function offerSelect(id, { allowNone = false, noneLabel = 'No specific product' } = {}) {
  const select = h('select', { id });
  if (allowNone) {
    select.appendChild(h('option', { value: '', text: noneLabel }));
  }
  for (const offer of state.offers) {
    select.appendChild(h('option', { value: String(offer.id), text: offer.product_name }));
  }
  const remembered = state.offers.find((offer) => offer.id === Number(state.lastOfferId));
  if (remembered) {
    select.value = String(remembered.id);
  } else if (!allowNone && state.offers[0]) {
    select.value = String(state.offers[0].id);
  }
  select.addEventListener('change', () => {
    if (select.value) {
      state.lastOfferId = Number(select.value);
      chrome.storage.local.set({ lastOfferId: state.lastOfferId });
    }
  });
  return select;
}

function field(labelText, control, hint) {
  return h('div', { class: 'field' },
    h('label', { class: 'label', for: control.id, text: labelText }),
    control,
    hint ? h('p', { class: 'hint', text: hint }) : null,
  );
}

/** Product + optional different list, for "Add to CRM". */
function crmPickers(prefix) {
  const product = offerSelect(`${prefix}Offer`);
  const list = h('select', { id: `${prefix}List` });

  function refreshLists() {
    const offer = selectedOffer(product);
    list.textContent = '';
    list.appendChild(h('option', { value: '', text: offer ? `${offer.product_name} prospects (default)` : 'Product list (default)' }));
    for (const item of state.lists) {
      if (offer && item.offer_id === offer.id) {
        continue;
      }
      list.appendChild(h('option', { value: String(item.id), text: `${item.name} (${item.contacts_count})` }));
    }
  }
  product.addEventListener('change', refreshLists);
  refreshLists();

  return {
    element: h('div', {}, field('Product you\'re finding customers for', product), field('Add to list', list, 'Contacts are organised in lists by product, ready for a campaign.')),
    payload: () => (list.value ? { list_id: Number(list.value), offer_id: product.value ? Number(product.value) : null } : { offer_id: Number(product.value) }),
  };
}

function noOffersView(what) {
  return h('div', { class: 'alert alert-info' },
    h('p', {}, h('strong', { text: 'Research a product first. ' }), `${what} are organised by the product you're promoting.`),
    h('div', { class: 'actions' }, h('button', { class: 'btn btn-primary btn-sm', type: 'button', text: 'Research a product', onclick: () => openUrl(`${state.siteUrl}/offers/create`) })),
  );
}

function errorAlert(result, fallback) {
  const data = result.data || {};
  const alert = h('div', { class: 'alert alert-error', role: 'alert' }, h('p', { text: AffilStackApi.errorMessage(result, fallback) }));
  const link = data.top_up_url || data.upgrade_url;
  if (link) {
    alert.appendChild(h('div', { class: 'actions' },
      h('button', { class: 'btn btn-primary btn-sm', type: 'button', text: data.code === 'insufficient_credits' ? 'Top up credits' : 'Upgrade plan', onclick: () => openUrl(link) })));
  }
  return alert;
}

// -------------------------------------------------------- Output blocks --

async function copyText(text, button) {
  try {
    await navigator.clipboard.writeText(text);
  } catch (e) {
    const area = h('textarea', { style: 'position:fixed;opacity:0' });
    area.value = text;
    document.body.appendChild(area);
    area.select();
    document.execCommand('copy');
    area.remove();
  }
  const old = button.textContent;
  button.textContent = 'Copied ✓';
  setTimeout(() => { button.textContent = old; }, 1500);
}

async function insertIntoPage(text) {
  if (!state.tab?.id) {
    return;
  }
  try {
    const [{ result }] = await chrome.scripting.executeScript({
      target: { tabId: state.tab.id },
      func: (value) => (globalThis.__affilstackExtractor ? globalThis.__affilstackExtractor.insertText(value) : { ok: false, reason: 'not_loaded' }),
      args: [text],
    });
    if (result && result.ok) {
      toast(result.target === 'note' ? 'Added to your connection note — review it, then send.' : 'Added to the message box — review it, then press Send.');
    } else {
      toast("Open LinkedIn's message box (or the \"Add a note\" box) first, then click Insert again.", { kind: 'error' });
    }
  } catch (e) {
    toast("Couldn't reach the page — reload LinkedIn and try again.", { kind: 'error' });
  }
}

function outputBlock(title, text, { limit = null, insert = false } = {}) {
  const copyBtn = h('button', { class: 'btn btn-secondary btn-sm', type: 'button', text: 'Copy' });
  copyBtn.addEventListener('click', () => copyText(text, copyBtn));
  const buttons = h('div', { class: 'actions' }, copyBtn,
    insert ? h('button', { class: 'btn btn-gold btn-sm', type: 'button', text: 'Insert', title: 'Insert into the open LinkedIn box', onclick: () => insertIntoPage(text) }) : null);
  const count = limit
    ? h('span', { class: `count${text.length > limit ? ' count-over' : ''}`, text: `${text.length}/${limit} characters` })
    : h('span', { class: 'count', text: `${text.length} characters` });

  return h('article', { class: 'out' },
    h('div', { class: 'out-head' }, h('h3', { text: title }), buttons),
    h('div', { class: 'out-body', text }),
    h('div', { class: 'out-foot' }, count),
  );
}

function updateCredits(data) {
  if (state.me && typeof data.credits_balance === 'number') {
    state.me.credits_balance = data.credits_balance;
    renderConnection();
  }
}

// ---------------------------------------------------------------- Maps --

function stars(rating, reviews) {
  if (rating === null || rating === undefined) {
    return null;
  }
  return h('span', {}, h('span', { class: 'stars', text: `★ ${rating}` }), reviews ? ` (${Number(reviews).toLocaleString()} reviews)` : '');
}

function mapsGateAlert() {
  return h('div', { class: 'alert alert-warn' },
    h('p', { text: "Adding Google Maps businesses isn't included in your current plan." }),
    h('div', { class: 'actions' }, h('button', { class: 'btn btn-primary btn-sm', type: 'button', text: 'See plans', onclick: () => openUrl(state.me?.billing_url || `${state.siteUrl}/billing`) })),
  );
}

async function lookupInCrm(items) {
  if (state.connection !== 'connected' || !items.length) {
    return;
  }
  const result = await AffilStackApi.request('/crm/contacts/lookup', { method: 'POST', body: { contacts: items.slice(0, 50) } });
  state.inCrm = {};
  if (result.ok) {
    for (const match of result.data.matches || []) {
      state.inCrm[match.index] = match;
    }
  }
}

function nextStepAfterAdd(data) {
  const box = h('div', { class: 'alert alert-success' },
    h('p', {}, h('strong', { text: 'Done. ' }), data.message || 'Added to your CRM.'),
    h('div', { class: 'actions' },
      data.list?.url ? h('button', { class: 'btn btn-primary btn-sm', type: 'button', text: 'View list in AffilStack', onclick: () => openUrl(data.list.url) }) : null,
      data.list?.campaign_url ? h('button', { class: 'btn-link', type: 'button', text: 'Create a campaign →', onclick: () => openUrl(data.list.campaign_url) }) : null,
    ),
  );
  return box;
}

async function addToCrm(contacts, pickers, button, resultBox, onDone) {
  resultBox.textContent = '';
  setBusy(button, true, 'Adding…');
  const result = await AffilStackApi.request('/crm/contacts', { method: 'POST', body: { ...pickers.payload(), contacts } });
  setBusy(button, false);

  if (!result.ok) {
    resultBox.appendChild(errorAlert(result, "Couldn't add to your CRM."));
    return;
  }
  const data = result.data;
  toast(data.created ? `Added to ${data.list.name}` : `Already in your CRM — filed in ${data.list.name}`, { linkText: 'View in AffilStack', linkUrl: data.list.url });
  resultBox.appendChild(nextStepAfterAdd(data));
  if (onDone) {
    onDone(data);
  }
}

function mapsPlaceView(place) {
  const match = state.inCrm[0];
  const resultBox = h('div', { 'aria-live': 'polite' });
  const card = h('div', { class: 'card' },
    h('div', { class: 'card-head' },
      h('span', { class: 'avatar avatar-maps', text: initials(place.name) }),
      h('div', { style: 'min-width:0;flex:1' },
        h('p', { class: 'card-title', text: place.name }),
        h('p', { class: 'card-sub' }, place.category || 'Business', place.rating !== null ? ' · ' : '', stars(place.rating, place.review_count)),
      ),
      match ? h('span', { class: 'badge badge-in', title: (match.lists || []).map((l) => l.name).join(', ') || 'In your CRM', text: 'In CRM' }) : null,
    ),
    h('ul', { class: 'meta' },
      place.address ? h('li', {}, h('span', { class: 'k', text: 'Address' }), place.address) : null,
      h('li', {}, h('span', { class: 'k', text: 'Phone' }), place.phone || '—'),
      h('li', {}, h('span', { class: 'k', text: 'Website' }), place.website ? place.website.replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, '') : '—'),
    ),
  );

  const parts = [h('p', { class: 'lead', text: 'Add this business to your CRM, filed under the product you want to sell them.' }), card];
  if (match) {
    parts.push(h('p', { class: 'hint', style: 'margin:-6px 0 12px', text: `Already in your CRM${match.lists?.length ? ` · on ${match.lists.map((l) => l.name).join(', ')}` : ''}. Adding again just files it in the list you pick.` }));
  }
  if (!state.canUseMaps) {
    parts.push(mapsGateAlert());
    return h('div', {}, parts);
  }
  if (!state.offers.length) {
    parts.push(noOffersView('Contacts'));
    return h('div', {}, parts);
  }

  const pickers = crmPickers('place');
  const button = h('button', { class: 'btn btn-primary btn-block', type: 'button', text: match ? 'Add to this list' : 'Add to CRM' });
  button.addEventListener('click', () => addToCrm([place], pickers, button, resultBox));
  parts.push(pickers.element, button, resultBox);
  return h('div', {}, parts);
}

function mapsListView(list) {
  const items = list.items;
  const resultBox = h('div', { 'aria-live': 'polite' });
  const checkboxes = [];
  const button = h('button', { class: 'btn btn-primary btn-block', type: 'button' });

  function refreshButton() {
    const count = checkboxes.filter((c) => c.checked).length;
    button.textContent = count ? `Add ${count} to CRM` : 'Select businesses to add';
    button.disabled = count === 0;
  }

  const ul = h('ul', { class: 'biz-list', 'aria-label': 'Businesses on this page' });
  items.forEach((item, index) => {
    const inCrm = state.inCrm[index];
    const box = h('input', { type: 'checkbox', checked: !inCrm, 'aria-describedby': `biz-sub-${index}` });
    box.addEventListener('change', () => { refreshButton(); syncAll(); });
    checkboxes.push(box);
    const sub = [item.category, item.rating !== null ? `★ ${item.rating}${item.review_count ? ` (${item.review_count})` : ''}` : null, item.phone].filter(Boolean).join(' · ');
    ul.appendChild(h('li', {},
      h('label', {},
        box,
        h('span', { style: 'flex:1;min-width:0' },
          h('span', { class: 'biz-name' }, item.name, inCrm ? ' ' : '', inCrm ? h('span', { class: 'badge badge-in', text: 'In CRM' }) : null),
          h('span', { class: 'biz-sub', id: `biz-sub-${index}`, style: 'display:block', text: sub || item.address || '' }),
        ),
      ),
    ));
  });

  const all = h('input', { type: 'checkbox', id: 'checkAll' });
  function syncAll() {
    all.checked = checkboxes.every((c) => c.checked);
    all.indeterminate = !all.checked && checkboxes.some((c) => c.checked);
  }
  all.addEventListener('change', () => { checkboxes.forEach((c) => { c.checked = all.checked; }); refreshButton(); });
  syncAll();
  refreshButton();

  const parts = [
    h('div', { class: 'list-head' },
      h('h2', { text: `${items.length} business${items.length === 1 ? '' : 'es'} found${list.query ? ` for “${list.query}”` : ''}` }),
      h('label', { class: 'check-all', for: 'checkAll' }, all, 'All'),
    ),
    ul,
  ];
  const already = Object.keys(state.inCrm).length;
  if (already) {
    parts.splice(1, 0, h('p', { class: 'hint', style: 'margin:-4px 0 8px', text: `${already} already in your CRM — unticked so you don't add them twice.` }));
  }
  if (!state.canUseMaps) {
    parts.push(mapsGateAlert());
    return h('div', {}, parts);
  }
  if (!state.offers.length) {
    parts.push(noOffersView('Contacts'));
    return h('div', {}, parts);
  }

  const pickers = crmPickers('bulk');
  button.addEventListener('click', () => {
    const chosen = items.filter((item, index) => checkboxes[index].checked);
    addToCrm(chosen, pickers, button, resultBox, (data) => {
      const chosenIndexes = items.map((item, index) => (checkboxes[index].checked ? index : null)).filter((i) => i !== null);
      (data.results || []).forEach((r) => {
        if (r.status === 'created' || r.status === 'duplicate') {
          const original = chosenIndexes[r.index];
          state.inCrm[original] = { contact_id: r.contact_id };
          checkboxes[original].checked = false;
        }
      });
      refreshButton();
      syncAll();
    });
  });
  parts.push(pickers.element, button, resultBox);
  return h('div', {}, parts);
}

// ------------------------------------------------------------ LinkedIn --

function profileCard(profile) {
  const isCompany = profile.type === 'company';
  const sub = isCompany
    ? [profile.industry, profile.company_size, profile.location].filter(Boolean).join(' · ')
    : profile.headline;
  return h('div', { class: 'card' },
    h('div', { class: 'card-head' },
      h('span', { class: 'avatar', text: initials(profile.name) }),
      h('div', { style: 'min-width:0;flex:1' },
        h('p', { class: 'card-title', text: profile.name }),
        sub ? h('p', { class: 'card-sub', text: sub }) : null,
      ),
      h('span', { class: 'badge badge-gold', text: isCompany ? 'Company' : 'Profile' }),
    ),
    !isCompany && (profile.current_role || profile.location)
      ? h('ul', { class: 'meta' },
        profile.current_role ? h('li', {}, h('span', { class: 'k', text: 'Role' }), [profile.current_role, profile.current_company].filter(Boolean).join(' at ')) : null,
        profile.location ? h('li', {}, h('span', { class: 'k', text: 'Location' }), profile.location) : null,
      )
      : null,
  );
}

function linkedinProfileView(profile, conversation) {
  const resultBox = h('div', { 'aria-live': 'polite' });
  const cost = state.me?.costs?.linkedin_message ?? 5;
  const parts = [
    h('p', { class: 'lead', text: profile.type === 'company'
      ? 'Write a personalised message to this company\'s decision-maker — in one click.'
      : 'Write a personalised connection note, first message and follow-ups for this person — in one click.' }),
    profileCard(profile),
  ];

  if (conversation) {
    parts.push(h('div', { class: 'alert alert-info' },
      h('p', { text: 'You have a conversation open on this page.' }),
      h('div', { class: 'actions' }, h('button', { class: 'btn btn-secondary btn-sm', type: 'button', text: 'Suggest a reply instead', onclick: () => showPage({ ...state.page, kind: 'linkedin_messaging', data: conversation }) })),
    ));
  }

  if (!state.offers.length) {
    parts.push(noOffersView('Messages'));
    return h('div', {}, parts);
  }

  const product = offerSelect('liOffer');
  const generate = h('button', { class: 'btn btn-primary btn-block', type: 'button' }, 'Generate message ', h('span', { class: 'cost', text: `· ${cost} credits` }));
  const save = h('button', { class: 'btn btn-secondary btn-block', type: 'button', style: 'margin-top:8px', text: 'Save to CRM list' });

  generate.addEventListener('click', async () => {
    resultBox.textContent = '';
    setBusy(generate, true, 'Writing your message…');
    const result = await AffilStackApi.request('/linkedin/message', { method: 'POST', body: { offer_id: Number(product.value), profile } });
    setBusy(generate, false);
    if (!result.ok) {
      resultBox.appendChild(errorAlert(result, "Couldn't write a message right now."));
      return;
    }
    const data = result.data;
    updateCredits(data);
    resultBox.append(
      h('div', { class: 'chips', style: 'margin-top:12px' },
        data.personalization ? h('span', { class: 'badge badge-gold', text: `Personalised: ${data.personalization}` }) : null),
      outputBlock('Connection note', data.connection_note, { limit: data.connection_note_limit, insert: true }),
      outputBlock('First message', data.message, { insert: true }),
      ...(data.follow_ups || []).map((f, i) => outputBlock(`Follow-up ${i + 1}${f.send_timing ? ` · ${f.send_timing}` : ''}`, f.message, { insert: true })),
      h('p', { class: 'hint', text: 'Copy or Insert, review, then send it yourself — AffilStack never sends on LinkedIn for you.' }),
    );
    resultBox.scrollIntoView({ block: 'start' });
  });

  save.addEventListener('click', async () => {
    setBusy(save, true, 'Saving…');
    const contact = profile.type === 'company'
      ? { source: 'linkedin', name: profile.name, company: profile.name, title: profile.industry || null, location: profile.location || null, linkedin_url: profile.url, website: profile.website || null }
      : { source: 'linkedin', name: profile.name, title: profile.headline || profile.current_role || null, company: profile.current_company || null, location: profile.location || null, linkedin_url: profile.url };
    const result = await AffilStackApi.request('/crm/contacts', { method: 'POST', body: { offer_id: Number(product.value), contacts: [contact] } });
    setBusy(save, false);
    if (result.ok) {
      toast(result.data.created ? `Saved to ${result.data.list.name}` : `Already in your CRM — filed in ${result.data.list.name}`, { linkText: 'View in AffilStack', linkUrl: result.data.list.url });
    } else {
      toast(AffilStackApi.errorMessage(result, "Couldn't save this contact."), { kind: 'error' });
    }
  });

  parts.push(field('Product you\'re promoting', product), generate, save, resultBox);
  return h('div', {}, parts);
}

function replyOutput(data, { insert }) {
  return h('div', { style: 'margin-top:12px' },
    h('div', { class: 'chips' },
      data.objection ? h('span', { class: 'badge badge-gold', text: `Handles: ${data.objection}` }) : null,
      data.next_step ? h('span', { class: 'badge badge-in', text: `Next step: ${data.next_step}` }) : null,
      data.uses_link ? h('span', { class: 'badge badge-gold', text: 'Includes your link' }) : null,
    ),
    outputBlock('Suggested reply', data.reply, { insert }),
  );
}

function messagingView(conversation) {
  const resultBox = h('div', { 'aria-live': 'polite' });
  const cost = state.me?.costs?.reply_suggestion ?? 3;
  const shown = conversation.messages.slice(-4);
  const parts = [
    h('p', { class: 'lead', text: `Get the best reply to ${conversation.participant || 'this prospect'} — handles objections and moves toward the sale.` }),
    h('div', { class: 'bubbles', 'aria-label': 'Latest messages' },
      shown.map((m) => h('div', { class: `bubble bubble-${m.role}` }, h('small', { text: m.role === 'me' ? 'You' : (m.sender || 'Them') }), m.text.length > 180 ? `${m.text.slice(0, 180)}…` : m.text))),
    h('p', { class: 'hint', style: 'margin:-6px 0 12px', text: `Reading the last ${conversation.messages.length} message${conversation.messages.length === 1 ? '' : 's'} of this conversation.` }),
  ];
  const product = offerSelect('msgOffer', { allowNone: true });
  const button = h('button', { class: 'btn btn-primary btn-block', type: 'button' }, 'Suggest reply ', h('span', { class: 'cost', text: `· ${cost} credits` }));
  button.addEventListener('click', async () => {
    resultBox.textContent = '';
    setBusy(button, true, 'Thinking of the best reply…');
    const result = await AffilStackApi.request('/linkedin/reply', {
      method: 'POST',
      body: {
        channel: 'linkedin',
        offer_id: product.value ? Number(product.value) : null,
        prospect_name: conversation.participant || null,
        messages: conversation.messages.map((m) => ({ role: m.role, text: m.text, sender: m.sender || null })),
      },
    });
    setBusy(button, false);
    if (!result.ok) {
      resultBox.appendChild(errorAlert(result, "Couldn't suggest a reply right now."));
      return;
    }
    updateCredits(result.data);
    resultBox.appendChild(replyOutput(result.data, { insert: true }));
    resultBox.scrollIntoView({ block: 'start' });
  });
  parts.push(field('Product you\'re promoting', product), button, resultBox);
  return h('div', {}, parts);
}

// ----------------------------------------------------------- Empty page --

function emptyView(title, message) {
  return h('div', { class: 'empty' },
    h('span', { class: 'empty-icon' }, icon('spark')),
    h('h2', { text: title }),
    h('p', { text: message }),
    h('div', { class: 'quick' },
      h('a', { href: 'https://www.google.com/maps', onclick: (e) => { e.preventDefault(); openUrl('https://www.google.com/maps'); } },
        h('strong', { text: 'Google Maps' }), h('span', { text: 'Search a keyword, open a business or list' })),
      h('a', { href: 'https://www.linkedin.com/', onclick: (e) => { e.preventDefault(); openUrl('https://www.linkedin.com/'); } },
        h('strong', { text: 'LinkedIn' }), h('span', { text: 'Open a profile, company or chat' })),
    ),
    h('p', { class: 'hint', style: 'margin-top:12px', text: 'Tip: your product\'s research page in AffilStack lists the best Maps and LinkedIn keywords to search.' }),
  );
}

function pageView(page) {
  switch (page.kind) {
    case 'maps_place':
      return mapsPlaceView(page.data);
    case 'maps_list':
      return mapsListView(page.data);
    case 'maps_empty':
      return emptyView('Search Google Maps first', 'Search for a keyword (e.g. "restaurants in Ikeja"), then open a business or keep the results list open and click the AffilStack icon again.');
    case 'linkedin_profile':
      return linkedinProfileView(page.data, page.conversation || null);
    case 'linkedin_company':
      return linkedinProfileView(page.data, null);
    case 'linkedin_messaging':
      return messagingView(page.data);
    case 'linkedin_messaging_empty':
      return emptyView('Open a conversation', 'Pick a conversation with a reply from your prospect, then click the AffilStack icon to get a suggested response.');
    case 'linkedin_other':
      return emptyView('Open a profile or company', 'Open someone\'s LinkedIn profile, a company page or a conversation, then click the AffilStack icon.');
    case 'restricted':
      return emptyView('This page can\'t be read', 'Chrome doesn\'t let extensions read this page. Open a Google Maps business or a LinkedIn profile, then click the AffilStack icon.');
    default:
      return emptyView('Nothing to add on this page', 'Open a Google Maps business or a LinkedIn profile, then click the AffilStack icon.');
  }
}

async function showPage(page) {
  state.page = page;
  const content = $('pageContent');
  content.textContent = '';

  if (state.connection === 'missing') {
    content.appendChild(connectView());
    return;
  }
  if (state.connection === 'invalid' || state.connection === 'offline') {
    content.appendChild(connectionProblemView());
    if (state.connection === 'invalid') {
      return;
    }
  }

  if (page.kind === 'maps_place') {
    await lookupInCrm([page.data]);
  } else if (page.kind === 'maps_list') {
    await lookupInCrm(page.data.items);
  }
  content.appendChild(pageView(page));
}

// ------------------------------------------------------------ Page read --

async function readPage(tab) {
  if (!tab?.id || !/^https?:/.test(tab.url || 'https:')) {
    return { kind: 'restricted', data: null, selection: '' };
  }
  try {
    await chrome.scripting.executeScript({ target: { tabId: tab.id }, files: ['extractor.js'] });
    const [{ result }] = await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      func: () => {
        const output = globalThis.__affilstackExtractor ? globalThis.__affilstackExtractor.extract() : { kind: 'other', data: null };
        output.selection = String(window.getSelection() || '').trim().slice(0, 10000);
        return output;
      },
    });
    return result || { kind: 'other', data: null, selection: '' };
  } catch (error) {
    // chrome://, the Web Store and some PDFs block script injection.
    return { kind: 'restricted', data: null, selection: '' };
  }
}

// ----------------------------------------------------------------- Tabs --

function selectTab(name) {
  for (const tabName of ['page', 'reply', 'clip']) {
    const tab = $(`tab-${tabName}`);
    const selected = tabName === name;
    tab.setAttribute('aria-selected', String(selected));
    tab.tabIndex = selected ? 0 : -1;
    $(`panel-${tabName}`).hidden = !selected;
  }
}

function setupTabs() {
  const names = ['page', 'reply', 'clip'];
  names.forEach((name, i) => {
    const tab = $(`tab-${name}`);
    tab.addEventListener('click', () => selectTab(name));
    tab.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        const next = names[(i + (e.key === 'ArrowRight' ? 1 : names.length - 1)) % names.length];
        selectTab(next);
        $(`tab-${next}`).focus();
        e.preventDefault();
      }
    });
  });
}

// --------------------------------------------------------- Reply helper --

function setupReplyHelper() {
  const buttons = Array.from(document.querySelectorAll('#channelGroup [role="radio"]'));
  const choose = (button) => {
    buttons.forEach((b) => { b.setAttribute('aria-checked', String(b === button)); b.tabIndex = b === button ? 0 : -1; });
    state.channel = button.dataset.channel;
  };
  buttons.forEach((button, i) => {
    button.addEventListener('click', () => choose(button));
    button.addEventListener('keydown', (e) => {
      if (['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp'].includes(e.key)) {
        const next = buttons[(i + (e.key === 'ArrowRight' || e.key === 'ArrowDown' ? 1 : buttons.length - 1)) % buttons.length];
        choose(next);
        next.focus();
        e.preventDefault();
      }
    });
  });

  $('replySubmit').addEventListener('click', async () => {
    const box = $('replyResult');
    box.textContent = '';
    const message = $('replyMessage').value.trim();
    if (state.connection === 'missing' || state.connection === 'invalid') {
      box.appendChild(state.connection === 'missing' ? connectView() : connectionProblemView());
      return;
    }
    if (!message) {
      box.appendChild(h('div', { class: 'alert alert-warn', role: 'alert' }, h('p', { text: 'Paste the message you want to reply to first.' })));
      $('replyMessage').focus();
      return;
    }
    const button = $('replySubmit');
    setBusy(button, true, 'Thinking of the best reply…');
    const result = await AffilStackApi.request('/linkedin/reply', {
      method: 'POST',
      body: { channel: state.channel, their_message: message, offer_id: $('replyOffer').value ? Number($('replyOffer').value) : null },
    });
    setBusy(button, false);
    if (!result.ok) {
      box.appendChild(errorAlert(result, "Couldn't suggest a reply right now."));
      return;
    }
    updateCredits(result.data);
    box.appendChild(replyOutput(result.data, { insert: state.channel === 'linkedin' && !!state.page?.composer }));
    box.scrollIntoView({ block: 'start' });
  });
}

function fillOfferSelect(select, noneValueLabel) {
  select.textContent = '';
  select.appendChild(h('option', { value: '', text: noneValueLabel }));
  for (const offer of state.offers) {
    select.appendChild(h('option', { value: String(offer.id), text: offer.product_name }));
  }
  if (state.offers.some((o) => o.id === Number(state.lastOfferId))) {
    select.value = String(state.lastOfferId);
  }
}

// ------------------------------------------------------------ Save page --

function setupClip() {
  $('clipSave').addEventListener('click', async () => {
    const button = $('clipSave');
    if (!state.tab?.url) {
      return;
    }
    if (state.connection === 'missing') {
      chrome.runtime.openOptionsPage();
      return;
    }
    setBusy(button, true, 'Saving…');
    const result = await AffilStackApi.request('/clips', {
      method: 'POST',
      body: {
        source_url: state.tab.url,
        title: state.tab.title || null,
        selected_text: state.page?.selection || null,
        page_type: $('clipType').value,
        offer_id: $('clipOffer').value ? Number($('clipOffer').value) : null,
      },
    });
    setBusy(button, false);
    if (result.ok) {
      toast('Saved to AffilStack.', { linkText: 'View captures', linkUrl: `${state.siteUrl}/extension` });
    } else {
      toast(AffilStackApi.errorMessage(result, 'Could not save this page.'), { kind: 'error' });
    }
  });
}

function renderClipInfo() {
  $('clipTitle').textContent = state.tab?.title || state.tab?.url || 'This page';
  const selection = state.page?.selection || '';
  $('clipSelection').textContent = selection
    ? `Selected text will be included: “${selection.length > 120 ? `${selection.slice(0, 120)}…` : selection}”`
    : 'Tip: select text on the page first to save it with the link.';
  if (state.tab?.url && /linkedin\.com\/(posts|feed\/update)/.test(state.tab.url)) {
    $('clipType').value = 'linkedin_post';
  }
}

// ----------------------------------------------------------------- Init --

async function checkConnection() {
  if (!state.token) {
    state.connection = 'missing';
    return;
  }
  const me = await AffilStackApi.request('/me');
  if (me.ok) {
    state.connection = 'connected';
    state.me = me.data;
    state.canUseMaps = me.data.can_use_google_maps !== false;
  } else {
    state.connection = me.status === 401 ? 'invalid' : 'offline';
    return;
  }
  const lists = await AffilStackApi.request('/crm/lists');
  if (lists.ok) {
    state.offers = lists.data.offers || [];
    state.lists = lists.data.lists || [];
    state.canUseMaps = lists.data.can_use_google_maps !== false;
  } else {
    // Older AffilStack sites without /crm/lists still have /offers.
    const offers = await AffilStackApi.request('/offers');
    state.offers = offers.ok ? offers.data.offers || [] : [];
  }
}

async function init() {
  setupTabs();
  setupReplyHelper();
  setupClip();

  const settings = await AffilStackApi.config();
  state.apiBaseUrl = settings.apiBaseUrl;
  state.token = settings.apiToken;
  state.siteUrl = AffilStackApi.siteUrl(settings.apiBaseUrl);
  state.lastOfferId = (await chrome.storage.local.get(['lastOfferId'])).lastOfferId || null;

  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  state.tab = tab || null;

  const [page] = await Promise.all([readPage(state.tab), checkConnection()]);
  renderConnection();

  fillOfferSelect($('replyOffer'), 'No specific product');
  fillOfferSelect($('clipOffer'), 'Not attached');
  renderClipInfo();

  $('pageLoading').hidden = true;
  await showPage(page);
}

init();
