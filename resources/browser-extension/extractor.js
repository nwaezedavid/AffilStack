// AffilStack page reader — injected into the CURRENT tab only when the user
// clicks the AffilStack toolbar icon (activeTab + scripting). It never runs on
// its own, never watches the page, and only reads what's visible on the page
// the user is looking at:
//   - Google Maps: the open business (name, category, address, phone, website,
//     rating, reviews, place id) or the visible results list.
//   - LinkedIn: the profile / company page being viewed, or the open
//     conversation (so the AI can suggest a reply).
// Nothing is sent anywhere from here: the popup shows the result and only
// sends what the user chooses to add or generate.
//
// Google and LinkedIn change their markup often, so every field has several
// fallbacks (stable aria-labels and data-item-id values first, class names
// last) and a missing field is simply left empty — it never throws.
(() => {
  const VERSION = '1.2.0';

  if (globalThis.__affilstackExtractor && globalThis.__affilstackExtractor.version === VERSION) {
    return;
  }

  const MAX_LIST_ITEMS = 60;
  const MAX_MESSAGES = 20;

  /** Collapses whitespace, strips LinkedIn's "…see more" and caps length. */
  function clean(value, max = 500) {
    if (value === null || value === undefined) {
      return '';
    }
    let text = String(value)
      .replace(/[​-‏-]/g, '')
      .replace(/\s+/g, ' ')
      .replace(/(…|\.\.\.)\s*(see more|more)$/i, '')
      .trim();
    if (text.length > max) {
      text = text.slice(0, max - 1).trimEnd() + '…';
    }
    return text;
  }

  function text(root, selectors, max) {
    for (const selector of [].concat(selectors)) {
      try {
        const el = root.querySelector(selector);
        const value = el ? clean(el.innerText || el.textContent, max) : '';
        if (value) {
          return value;
        }
      } catch (e) {
        // An unsupported selector in an older browser — try the next one.
      }
    }
    return '';
  }

  function attr(root, selectors, name) {
    for (const selector of [].concat(selectors)) {
      try {
        const el = root.querySelector(selector);
        const value = el ? el.getAttribute(name) : null;
        if (value) {
          return value.trim();
        }
      } catch (e) {
        // ignore
      }
    }
    return '';
  }

  /** "Address: 12 Allen Ave" → "12 Allen Ave". */
  function stripLabel(value) {
    return clean(String(value || '').replace(/^[^:]{2,20}:\s*/, ''));
  }

  function parseNumber(value) {
    const match = String(value || '').replace(/,/g, '.').match(/\d+(\.\d+)?/);
    return match ? parseFloat(match[0]) : null;
  }

  function parseCount(value) {
    const digits = String(value || '').replace(/[^\d]/g, '');
    return digits ? parseInt(digits, 10) : null;
  }

  /** Google wraps outbound links as /url?q=<real url>. */
  function unwrapGoogleUrl(href) {
    if (!href) {
      return '';
    }
    try {
      const url = new URL(href, 'https://www.google.com');
      if (/google\./.test(url.hostname) && url.pathname === '/url') {
        return url.searchParams.get('q') || url.searchParams.get('url') || '';
      }
      if (/google\./.test(url.hostname)) {
        return '';
      }
      return url.href;
    } catch (e) {
      return '';
    }
  }

  /**
   * Google identifiers hidden in a Maps URL:
   *   !19sChIJ…           → the Places API place id (what Lead Finder stores)
   *   !1s0x…:0x…          → the feature id; its second half is the CID
   *   ?cid=123 / ludocid  → the CID directly
   */
  function mapsIds(href) {
    const ids = { place_id: '', ftid: '', cid: '' };
    if (!href) {
      return ids;
    }
    let decoded = href;
    try {
      decoded = decodeURIComponent(href);
    } catch (e) {
      // keep raw
    }
    const placeId = decoded.match(/!19s(ChIJ[A-Za-z0-9_-]+)/) || decoded.match(/place_id[:=](ChIJ[A-Za-z0-9_-]+)/);
    if (placeId) {
      ids.place_id = placeId[1];
    }
    const ftid = decoded.match(/!1s(0x[0-9a-f]+:0x[0-9a-f]+)/i) || decoded.match(/ftid=(0x[0-9a-f]+:0x[0-9a-f]+)/i);
    if (ftid) {
      ids.ftid = ftid[1].toLowerCase();
      try {
        ids.cid = BigInt(ids.ftid.split(':')[1]).toString();
      } catch (e) {
        // BigInt unsupported — leave the CID empty.
      }
    }
    const cid = decoded.match(/[?&](?:cid|ludocid)=(\d+)/);
    if (cid) {
      ids.cid = cid[1];
    }
    return ids;
  }

  function placeNameFromUrl(href) {
    const match = String(href || '').match(/\/maps\/place\/([^/@?]+)/);
    if (!match) {
      return '';
    }
    try {
      return clean(decodeURIComponent(match[1].replace(/\+/g, ' ')), 255);
    } catch (e) {
      return '';
    }
  }

  const PHONE_RE = /(\+?\(?\d[\d\s().-]{6,}\d)/;

  // ---------------------------------------------------------------- Maps --

  function extractMapsPlace(doc, href) {
    const main = doc.querySelector('div[role="main"][aria-label]') || doc;
    const name = text(main, ['h1.DUwDvf', 'h1.fontHeadlineLarge', 'h1'], 255)
      || clean(main !== doc ? main.getAttribute('aria-label') : '', 255)
      || placeNameFromUrl(href);

    if (!name) {
      return null;
    }

    const category = text(main, ['button.DkEaL', 'button[jsaction*="category"]', 'span.DkEaL'], 255);

    let rating = parseNumber(text(main, ['div.F7nice span[aria-hidden="true"]', 'span.ceNzKf'], 10));
    if (rating === null) {
      rating = parseNumber(attr(main, ['span[role="img"][aria-label*="star" i]'], 'aria-label'));
    }

    let reviewCount = parseCount(attr(main, ['div.F7nice span[aria-label*="review" i]', 'button[aria-label*="review" i]'], 'aria-label'));
    if (reviewCount === null) {
      const reviewText = text(main, ['div.F7nice span:not([aria-hidden])', 'div.F7nice'], 60).match(/\(([\d.,\s]+)\)/);
      reviewCount = reviewText ? parseCount(reviewText[1]) : null;
    }

    const address = stripLabel(attr(main, ['[data-item-id="address"]'], 'aria-label'))
      || text(main, ['[data-item-id="address"] .Io6YTe', '[data-item-id="address"]'], 500);

    const phoneEl = main.querySelector('[data-item-id^="phone:tel:"], [data-tooltip*="phone" i][aria-label]');
    let phone = '';
    if (phoneEl) {
      phone = stripLabel(phoneEl.getAttribute('aria-label'))
        || clean(phoneEl.innerText)
        || (phoneEl.getAttribute('data-item-id') || '').replace('phone:tel:', '');
    }

    const websiteEl = main.querySelector('a[data-item-id="authority"], a[aria-label^="Website" i], a[data-tooltip*="website" i]');
    const website = websiteEl ? unwrapGoogleUrl(websiteEl.getAttribute('href')) : '';

    const ids = mapsIds(href);

    return {
      source: 'google_maps',
      name,
      category,
      address,
      phone: clean(phone, 60),
      website,
      rating,
      review_count: reviewCount,
      maps_url: ids.place_id ? `https://www.google.com/maps/place/?q=place_id:${ids.place_id}` : href,
      place_id: ids.place_id,
      cid: ids.cid,
      ftid: ids.ftid,
    };
  }

  function extractMapsListItem(article) {
    const link = article.querySelector('a.hfpxzc[href*="/maps/place/"]') || article.querySelector('a[href*="/maps/place/"]');
    if (!link) {
      return null;
    }
    const href = link.href || link.getAttribute('href') || '';
    const name = clean(link.getAttribute('aria-label'), 255)
      || text(article, ['.qBF1Pd', '.fontHeadlineSmall'], 255)
      || clean(article.getAttribute('aria-label'), 255)
      || placeNameFromUrl(href);
    if (!name) {
      return null;
    }

    const starsLabel = attr(article, ['span[role="img"][aria-label*="star" i]'], 'aria-label');
    let rating = parseNumber(text(article, ['.MW4etd'], 10));
    if (rating === null && starsLabel) {
      rating = parseNumber(starsLabel);
    }
    let reviewCount = parseCount(text(article, ['.UY7F9'], 30));
    if (reviewCount === null && starsLabel) {
      const reviews = starsLabel.match(/([\d.,]+)\s*review/i);
      reviewCount = reviews ? parseCount(reviews[1]) : null;
    }

    // Info lines look like "Restaurant · 12 Allen Ave" and
    // "Open ⋅ Closes 10 pm · 0803 123 4567".
    const lines = Array.from(article.querySelectorAll('.W4Efsd'))
      .filter((el) => !el.querySelector('.W4Efsd'))
      .map((el) => clean(el.innerText || el.textContent, 300))
      .filter(Boolean);
    const segments = lines.map((line) => line.split(/\s*[·⋅]\s*/).map((s) => s.trim()).filter(Boolean));

    let category = '';
    let address = '';
    for (const parts of segments) {
      // Drop the "4.5(120)" rating run and price levels like "₦₦" or "$$".
      const usable = parts.filter((part) => !/^\d([.,]\d)?\s*\(/.test(part) && !/^\(?[\d.,]+\)?$/.test(part) && !/^[^\p{L}\p{N}\s]{1,4}$/u.test(part));
      if (usable.length && !/^(open|closed|closes|opens)/i.test(usable[0]) && !PHONE_RE.test(usable[0])) {
        category = usable[0];
        address = usable[1] && !PHONE_RE.test(usable[1]) && !/^(open|closed|closes|opens)/i.test(usable[1]) ? usable[1] : '';
        break;
      }
    }

    let phone = text(article, ['.UsdlK'], 60);
    if (!phone) {
      const match = lines.join(' · ').match(PHONE_RE);
      phone = match ? clean(match[1], 60) : '';
    }

    const websiteEl = article.querySelector('a[data-value="Website"], a.lcr4fd, a[aria-label*="website" i]');
    const website = websiteEl ? unwrapGoogleUrl(websiteEl.getAttribute('href')) : '';
    const ids = mapsIds(href);

    return {
      source: 'google_maps',
      name,
      category: clean(category, 255),
      address: clean(address, 500),
      phone,
      website,
      rating,
      review_count: reviewCount,
      maps_url: ids.place_id ? `https://www.google.com/maps/place/?q=place_id:${ids.place_id}` : href,
      place_id: ids.place_id,
      cid: ids.cid,
      ftid: ids.ftid,
    };
  }

  function extractMapsList(doc) {
    const feed = doc.querySelector('div[role="feed"]');
    if (!feed) {
      return null;
    }
    let articles = Array.from(feed.querySelectorAll('div[role="article"]'));
    if (!articles.length) {
      articles = Array.from(feed.querySelectorAll('.Nv2PK'));
    }
    if (!articles.length) {
      articles = Array.from(feed.querySelectorAll('a[href*="/maps/place/"]')).map((a) => a.parentElement || a);
    }

    const seen = new Set();
    const items = [];
    for (const article of articles) {
      const item = extractMapsListItem(article);
      if (!item) {
        continue;
      }
      const key = item.place_id || item.cid || item.name.toLowerCase();
      if (seen.has(key)) {
        continue;
      }
      seen.add(key);
      items.push(item);
      if (items.length >= MAX_LIST_ITEMS) {
        break;
      }
    }

    const label = clean(feed.getAttribute('aria-label'), 200).replace(/^Results for\s+/i, '');

    return { query: label, items };
  }

  // ------------------------------------------------------------ LinkedIn --

  function sectionByAnchor(root, id) {
    const anchor = root.querySelector(`#${id}`);
    return anchor ? anchor.closest('section') : null;
  }

  function visibleSpans(el) {
    const spans = Array.from(el.querySelectorAll('span[aria-hidden="true"]')).map((s) => clean(s.textContent, 300)).filter(Boolean);
    return spans.filter((value, index) => spans.indexOf(value) === index);
  }

  function nameFromTitle(doc) {
    return clean((doc.title || '').replace(/^\(\d+\)\s*/, '').split(/\s[|–-]\s/)[0], 255);
  }

  function extractLinkedInProfile(doc, href) {
    const main = doc.querySelector('main') || doc;
    const name = text(main, ['h1.text-heading-xlarge', 'h1'], 255) || nameFromTitle(doc);
    if (!name) {
      return null;
    }

    const headline = text(main, ['.text-body-medium.break-words', '[data-generated-suggestion-target]', '.pv-text-details__left-panel .text-body-medium'], 500);
    const location = text(main, ['span.text-body-small.inline.t-black--light.break-words', '.pv-text-details__left-panel span.text-body-small'], 255);

    let currentCompany = '';
    const companyLabel = attr(main, ['button[aria-label^="Current company" i]', '[aria-label^="Current company" i]'], 'aria-label');
    if (companyLabel) {
      currentCompany = clean(companyLabel.replace(/^Current company:\s*/i, '').replace(/\.\s*Click.*$/i, ''), 255);
    }

    let about = '';
    const aboutSection = sectionByAnchor(main, 'about');
    if (aboutSection) {
      about = text(aboutSection, ['.inline-show-more-text span[aria-hidden="true"]', '.display-flex.ph5 span[aria-hidden="true"]', 'div.full-width span[aria-hidden="true"]'], 2000);
    }

    const experience = [];
    let currentRole = '';
    const experienceSection = sectionByAnchor(main, 'experience');
    if (experienceSection) {
      const items = Array.from(experienceSection.querySelectorAll('li.artdeco-list__item, li.pvs-list__paged-list-item'))
        .filter((li) => !li.parentElement || !li.parentElement.closest('li.artdeco-list__item, li.pvs-list__paged-list-item'));
      for (const li of items.slice(0, 5)) {
        const parts = visibleSpans(li).slice(0, 4);
        if (parts.length) {
          experience.push(clean(parts.join(' · '), 300));
        }
        if (!currentRole && parts[0]) {
          currentRole = parts[0];
          if (!currentCompany && parts[1]) {
            currentCompany = clean(parts[1].split(/\s·\s/)[0], 255);
          }
        }
      }
    }

    return {
      type: 'person',
      name,
      url: canonicalLinkedInUrl(href),
      headline,
      location,
      about,
      current_role: clean(currentRole, 255),
      current_company: currentCompany,
      experience,
    };
  }

  function extractLinkedInCompany(doc, href) {
    const main = doc.querySelector('main') || doc;
    const name = text(main, ['h1.org-top-card-summary__title', '.org-top-card-summary__title', 'h1'], 255) || nameFromTitle(doc);
    if (!name) {
      return null;
    }

    const headline = text(main, ['.org-top-card-summary__tagline', 'p.org-top-card-summary__tagline'], 500);
    const info = Array.from(main.querySelectorAll('.org-top-card-summary-info-list__info-item')).map((el) => clean(el.textContent, 200)).filter(Boolean);
    let industry = info.find((item) => !/followers|employees|\d/.test(item)) || '';
    let location = info.find((item) => item !== industry && !/followers|employees/i.test(item) && /,/.test(item)) || '';
    const followers = info.find((item) => /followers/i.test(item)) || '';
    let companySize = info.find((item) => /employees/i.test(item)) || '';
    let website = '';

    // The /about/ tab lists details as <dt>Label</dt><dd>Value</dd>.
    for (const dt of Array.from(main.querySelectorAll('dl dt'))) {
      const label = clean(dt.textContent, 60).toLowerCase();
      const dd = dt.nextElementSibling && dt.nextElementSibling.tagName === 'DD' ? dt.nextElementSibling : null;
      const value = dd ? clean(dd.textContent, 300) : '';
      if (!value) {
        continue;
      }
      if (label.startsWith('industry')) {
        industry = value;
      } else if (label.startsWith('company size')) {
        companySize = value;
      } else if (label.startsWith('headquarters')) {
        location = value;
      } else if (label.startsWith('website')) {
        website = (dd.querySelector('a') && dd.querySelector('a').getAttribute('href')) || value;
      }
    }

    const description = text(main, [
      '.org-about-us-organization-description__text',
      'section.org-about-module p',
      '.org-page-details-module__card-spacing p.break-words',
      'p.break-words.white-space-pre-wrap',
    ], 2000);

    return {
      type: 'company',
      name,
      url: canonicalLinkedInUrl(href),
      headline,
      industry: clean(industry, 255),
      location: clean(location, 255),
      company_size: clean(companySize, 255),
      followers: clean(followers, 100),
      company_description: description,
      website: clean(website, 500),
    };
  }

  function canonicalLinkedInUrl(href) {
    const match = String(href || '').match(/^(https?:\/\/[^/]*linkedin\.com\/(?:in|company)\/[^/?#]+)/i);
    return match ? match[1] + '/' : String(href || '');
  }

  function extractLinkedInConversation(doc) {
    const list = doc.querySelector('.msg-s-message-list, .msg-s-message-list-content');
    if (!list) {
      return null;
    }
    const scope = list.closest('.msg-thread, .msg-overlay-conversation-bubble, .scaffold-layout__detail') || doc;

    const participant = text(scope, [
      '#thread-detail-jump-target',
      '.msg-entity-lockup__entity-title',
      '.msg-overlay-bubble-header__title',
      '.msg-thread__link-to-profile h2',
    ], 255) || text(doc, ['.msg-conversations-container__convo-item--active .msg-conversation-listitem__participant-names'], 255);
    const me = clean(attr(doc, ['img.global-nav__me-photo', '.global-nav__me img'], 'alt'), 255);

    const messages = [];
    let sender = '';
    let senderIsOther = null;
    const events = list.querySelectorAll('li.msg-s-message-list__event');
    const rows = events.length ? Array.from(events) : Array.from(list.querySelectorAll('.msg-s-event-listitem'));

    for (const row of rows) {
      const groupName = text(row, ['.msg-s-message-group__name', '.msg-s-message-group__profile-link'], 255);
      if (groupName) {
        sender = groupName;
        senderIsOther = null;
      }
      const items = row.matches('.msg-s-event-listitem') ? [row] : Array.from(row.querySelectorAll('.msg-s-event-listitem'));
      for (const item of items) {
        const body = text(item, ['.msg-s-event-listitem__body', 'p'], 2000);
        if (!body) {
          continue;
        }
        let role;
        if (item.classList.contains('msg-s-event-listitem--other')) {
          role = 'them';
        } else if (item.classList.contains('msg-s-event-listitem--self')) {
          role = 'me';
        } else if (sender && me && sender.toLowerCase() === me.toLowerCase()) {
          role = 'me';
        } else if (sender && participant && sender.toLowerCase() === participant.toLowerCase()) {
          role = 'them';
        } else if (senderIsOther !== null) {
          role = senderIsOther ? 'them' : 'me';
        } else {
          role = sender && participant ? 'me' : 'them';
        }
        senderIsOther = role === 'them';
        messages.push({ role, sender: role === 'them' ? (sender || participant) : (sender || me || 'Me'), text: body });
      }
    }

    return {
      participant,
      me,
      messages: messages.slice(-MAX_MESSAGES),
    };
  }

  function findComposer(doc) {
    const note = doc.querySelector('[role="dialog"] textarea#custom-message, [role="dialog"] textarea[name="message"], textarea#custom-message');
    if (note) {
      return { el: note, target: 'note' };
    }
    const boxes = Array.from(doc.querySelectorAll('.msg-form__contenteditable[contenteditable="true"], div[role="textbox"][contenteditable="true"][aria-label*="message" i]'));
    if (boxes.length) {
      return { el: boxes[boxes.length - 1], target: 'message' };
    }
    return null;
  }

  // -------------------------------------------------------------- Public --

  function extract(options = {}) {
    const doc = options.doc || document;
    const href = options.url || (doc.location ? doc.location.href : '');
    let url;
    try {
      url = new URL(href);
    } catch (e) {
      return { kind: 'other', url: href, title: doc.title || '', data: null };
    }
    const host = url.hostname.toLowerCase();
    const path = url.pathname;
    const base = { url: href, title: clean(doc.title, 255), composer: false };

    try {
      if (/(^|\.)google\.[a-z.]+$/.test(host) && path.startsWith('/maps')) {
        if (path.startsWith('/maps/place/') || doc.querySelector('[data-item-id="address"], [data-item-id^="phone:tel:"]')) {
          const place = extractMapsPlace(doc, href);
          if (place) {
            return { ...base, kind: 'maps_place', data: place };
          }
        }
        const list = extractMapsList(doc);
        if (list && list.items.length) {
          return { ...base, kind: 'maps_list', data: list };
        }
        return { ...base, kind: 'maps_empty', data: null };
      }

      if (/(^|\.)linkedin\.com$/.test(host)) {
        const composer = !!findComposer(doc);
        if (path.startsWith('/messaging')) {
          const conversation = extractLinkedInConversation(doc);
          return { ...base, composer, kind: conversation && conversation.messages.length ? 'linkedin_messaging' : 'linkedin_messaging_empty', data: conversation };
        }
        if (path.startsWith('/in/')) {
          const profile = extractLinkedInProfile(doc, href);
          // An open chat bubble on a profile page is offered as the reply helper too.
          const conversation = extractLinkedInConversation(doc);
          if (profile) {
            return { ...base, composer, kind: 'linkedin_profile', data: profile, conversation: conversation && conversation.messages.length ? conversation : null };
          }
        }
        if (path.startsWith('/company/') || path.startsWith('/school/') || path.startsWith('/showcase/')) {
          const company = extractLinkedInCompany(doc, href);
          if (company) {
            return { ...base, composer, kind: 'linkedin_company', data: company };
          }
        }
        const conversation = extractLinkedInConversation(doc);
        if (conversation && conversation.messages.length) {
          return { ...base, composer, kind: 'linkedin_messaging', data: conversation };
        }
        return { ...base, composer, kind: 'linkedin_other', data: null };
      }
    } catch (error) {
      return { ...base, kind: 'error', data: null, error: String(error && error.message || error) };
    }

    return { ...base, kind: 'other', data: null };
  }

  /**
   * Puts text into LinkedIn's open connection-note box or message box. The
   * user still reviews it and presses Send themselves.
   */
  function insertText(value, options = {}) {
    const doc = options.doc || document;
    const found = findComposer(doc);
    if (!found) {
      return { ok: false, reason: 'no_composer' };
    }
    const { el, target } = found;
    el.focus();
    if (el.tagName === 'TEXTAREA' || el.tagName === 'INPUT') {
      const setter = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(el), 'value');
      if (setter && setter.set) {
        setter.set.call(el, value);
      } else {
        el.value = value;
      }
    } else {
      el.innerHTML = '';
      for (const line of String(value).split('\n')) {
        const p = doc.createElement('p');
        if (line) {
          p.textContent = line;
        } else {
          p.appendChild(doc.createElement('br'));
        }
        el.appendChild(p);
      }
    }
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    return { ok: true, target };
  }

  globalThis.__affilstackExtractor = { version: VERSION, extract, insertText, mapsIds };
})();
