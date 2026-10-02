// End-to-end tests for the extension popup and options page
// (resources/browser-extension/popup.* / options.*), run in Chromium with a
// small stand-in for the chrome.* extension APIs and a mocked AffilStack API.
// The "current tab" each scenario sees is the REAL extractor's output for the
// fixture pages in ./fixtures, so popup + extractor are tested together.
//
// Run:
//   NODE_PATH=/tmp/claude-0/pw/node_modules node --test tests/browser-extension/*.test.cjs
// Screenshots of every popup state are written to $SCREENSHOT_DIR
// (default: <os tmp>/affilstack-extension-shots).
const { test, before, after } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '../..');
const extensionDir = path.join(root, 'resources/browser-extension');
const shotDir = process.env.SCREENSHOT_DIR || path.join(os.tmpdir(), 'affilstack-extension-shots');
const ORIGIN = 'http://ext.test';
const fixture = (name) => fs.readFileSync(path.join(__dirname, 'fixtures', name), 'utf8');

const URLS = {
  place: 'https://www.google.com/maps/place/Mama+Put+Kitchen/@6.6,3.35,17z/data=!4m6!3m5!1s0x103b93a1d2f4c5e7:0x9d7c4e2b1a3f5c68!19sChIJ5cX00qGTOxARaFw-K0eTn0',
  list: 'https://www.google.com/maps/search/restaurants+in+Ikeja/@6.60,3.35,14z',
  profile: 'https://www.linkedin.com/in/ada-obi-12345/',
  company: 'https://www.linkedin.com/company/paystack/about/',
  messaging: 'https://www.linkedin.com/messaging/thread/2-ZmFrZS10aHJlYWQ=/',
};

let browser;
const pages = {};

const ME = {
  name: 'Nwaeze David', email: 'nwaeze@example.com', credits_balance: 120, can_use_google_maps: true, is_seat: false,
  costs: { linkedin_message: 5, reply_suggestion: 3 },
  dashboard_url: 'http://ext.test/dashboard', extension_url: 'http://ext.test/extension', billing_url: 'http://ext.test/billing',
};
const LISTS = {
  lists: [
    { id: 11, name: 'Acme POS prospects', offer_id: 1, color: 'brand', contacts_count: 42, url: 'http://ext.test/crm/lists/11' },
    { id: 12, name: 'Lagos restaurants', offer_id: null, color: 'emerald', contacts_count: 7, url: 'http://ext.test/crm/lists/12' },
  ],
  offers: [{ id: 1, product_name: 'Acme POS', list_id: 11 }, { id: 2, product_name: 'Bookkeeper Pro', list_id: null }],
  can_use_google_maps: true,
};

before(async () => {
  fs.mkdirSync(shotDir, { recursive: true });
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium' });

  // Run the real extractor over each fixture once.
  const reader = await browser.newPage();
  const extractor = path.join(extensionDir, 'extractor.js');
  for (const [key, file] of Object.entries({ place: 'maps-place.html', list: 'maps-list.html', profile: 'linkedin-profile.html', company: 'linkedin-company.html', messaging: 'linkedin-messaging.html' })) {
    await reader.setContent(fixture(file));
    await reader.addScriptTag({ path: extractor });
    pages[key] = await reader.evaluate((u) => globalThis.__affilstackExtractor.extract({ url: u }), URLS[key]);
    pages[key].selection = '';
  }
  pages.other = { kind: 'other', url: 'https://example.com/', title: 'Example', data: null, selection: '' };
  await reader.close();
});

after(async () => {
  await browser.close();
});

/**
 * Opens the popup with a fake chrome.* and a mocked API.
 * @param {object} o
 */
async function openPopup({ page: tabPage, token = 'aff_test', api = {}, file = 'popup.html', viewport = { width: 380, height: 600 } }) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  const calls = [];

  await page.addInitScript(({ tabPage, token, origin }) => {
    const store = { apiBaseUrl: `${origin}/api`, ...(token ? { apiToken: token } : {}) };
    const pick = (keys) => Object.fromEntries([].concat(keys).filter((k) => k in store).map((k) => [k, store[k]]));
    window.__opened = [];
    window.__inserted = [];
    window.__store = store;
    window.chrome = {
      storage: { local: {
        get: async (keys) => pick(keys),
        set: async (values) => { Object.assign(store, values); },
        remove: async (keys) => { [].concat(keys).forEach((k) => delete store[k]); },
      } },
      tabs: {
        query: async () => [{ id: 7, url: tabPage.url, title: tabPage.title }],
        create: ({ url }) => { window.__opened.push(url); },
      },
      runtime: { openOptionsPage: () => { window.__optionsOpened = true; } },
      scripting: {
        executeScript: async ({ files, args }) => {
          if (files) {
            return [{ result: undefined }];
          }
          if (args) {
            window.__inserted.push(args[0]);
            return [{ result: tabPage.composer ? { ok: true, target: 'message' } : { ok: false, reason: 'no_composer' } }];
          }
          return [{ result: tabPage }];
        },
      },
    };
  }, { tabPage, token, origin: ORIGIN });

  await page.route(`${ORIGIN}/**`, async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    if (url.pathname.startsWith('/api/')) {
      const key = `${request.method()} ${url.pathname.replace('/api', '')}`;
      const body = request.postData() ? JSON.parse(request.postData()) : null;
      calls.push({ key, body, auth: request.headers().authorization });
      const handler = api[key];
      const defaults = { 'GET /me': [200, ME], 'GET /crm/lists': [200, LISTS], 'POST /crm/contacts/lookup': [200, { matches: [] }] };
      const [status, json] = handler ? (typeof handler === 'function' ? handler(body) : handler) : (defaults[key] || [404, { message: 'Not found' }]);
      return route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(json) });
    }
    const filePath = path.join(extensionDir, url.pathname.replace(/^\/ext\//, ''));
    return route.fulfill({ path: filePath });
  });

  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.goto(`${ORIGIN}/ext/${file}`);
  await page.waitForFunction(() => document.getElementById('pageLoading') ? document.getElementById('pageLoading').hidden : true);
  return { page, calls, errors, context };
}

async function shot(page, name) {
  await page.screenshot({ path: path.join(shotDir, `${name}.png`), fullPage: true });
}

test('not connected: explains how to connect and opens the settings', async () => {
  const { page, errors, context } = await openPopup({ page: pages.other, token: null });

  await page.getByText('Connect your AffilStack account').waitFor();
  assert.equal(await page.textContent('#connectionPillText'), 'Not connected');
  await shot(page, '01-not-connected');
  await page.getByRole('button', { name: 'Add your token' }).click();
  assert.equal(await page.evaluate(() => window.__optionsOpened), true);
  await page.getByRole('button', { name: 'Get a token' }).click();
  assert.deepEqual(await page.evaluate(() => window.__opened), ['http://ext.test/extension']);
  assert.deepEqual(errors, []);
  await context.close();
});

test('invalid token: red status with a fix link', async () => {
  const { page, context } = await openPopup({ page: pages.place, api: { 'GET /me': [401, { message: 'Invalid or revoked token.' }] } });

  await page.getByText('Your token was rejected.').waitFor();
  assert.equal(await page.textContent('#connectionPillText'), 'Token rejected');
  assert.match(await page.getAttribute('#connectionPill', 'class'), /pill-failed/);
  await shot(page, '02-invalid-token');
  await context.close();
});

test('empty state on an unrelated page', async () => {
  const { page, context } = await openPopup({ page: pages.other });

  await page.getByText('Open a Google Maps business or a LinkedIn profile, then click the AffilStack icon.').waitFor();
  assert.equal(await page.textContent('#connectionPillText'), 'Connected');
  assert.match(await page.textContent('#connectionText'), /Connected as nwaeze@example.com/);
  assert.equal(await page.textContent('#creditsText'), '120 credits left');
  await shot(page, '03-empty-state');
  await context.close();
});

test('Maps place: shows the business and adds it to the product list', async () => {
  const { page, calls, errors, context } = await openPopup({
    page: pages.place,
    api: {
      'POST /crm/contacts': [201, {
        message: '1 new contact added — filed in Acme POS prospects.', created: 1, duplicates: 0, failed: 0, added_to_list: 1,
        results: [{ index: 0, status: 'created', contact_id: 5, added_to_list: true }],
        list: { id: 11, name: 'Acme POS prospects', url: 'http://ext.test/crm/lists/11', campaign_url: 'http://ext.test/crm/campaigns/create?list=11' },
      }],
    },
  });

  await page.getByText('Mama Put Kitchen').first().waitFor();
  assert.match(await page.textContent('#pageContent'), /Nigerian restaurant/);
  assert.match(await page.textContent('#pageContent'), /0803 123 4567/);
  assert.equal(await page.inputValue('#placeOffer'), '1');
  await shot(page, '04-maps-place');

  await page.getByRole('button', { name: 'Add to CRM' }).click();
  await page.getByText('Added to Acme POS prospects').waitFor();
  const post = calls.find((c) => c.key === 'POST /crm/contacts');
  assert.equal(post.auth, 'Bearer aff_test');
  assert.equal(post.body.offer_id, 1);
  assert.equal(post.body.contacts[0].place_id, 'ChIJ5cX00qGTOxARaFw-K0eTn0');
  assert.equal(post.body.contacts[0].phone, '0803 123 4567');
  await shot(page, '05-maps-place-added');

  await page.getByRole('link', { name: 'View in AffilStack' }).click();
  assert.deepEqual(await page.evaluate(() => window.__opened), ['http://ext.test/crm/lists/11']);
  assert.deepEqual(errors, []);
  await context.close();
});

test('Maps place: a different list can be chosen and remembered product is used', async () => {
  const { page, calls, context } = await openPopup({ page: pages.place, api: { 'POST /crm/contacts': [200, { message: 'ok', created: 0, duplicates: 1, results: [], list: { id: 12, name: 'Lagos restaurants', url: 'u' } }] } });

  await page.selectOption('#placeOffer', '2');
  await page.selectOption('#placeList', '12');
  await page.getByRole('button', { name: 'Add to CRM' }).click();
  await page.getByText('Already in your CRM — filed in Lagos restaurants').waitFor();
  const post = calls.find((c) => c.key === 'POST /crm/contacts');
  assert.equal(post.body.list_id, 12);
  assert.equal(await page.evaluate(() => window.__store.lastOfferId), 2);
  await context.close();
});

test('Maps results list: bulk add with already-in-CRM businesses unticked', async () => {
  const { page, calls, context } = await openPopup({
    page: pages.list,
    api: {
      'POST /crm/contacts/lookup': [200, { matches: [{ index: 1, contact_id: 9, name: 'Bukka Hut Allen', matched_by: 'place_id', lists: [{ id: 11, name: 'Acme POS prospects' }] }] }],
      'POST /crm/contacts': (body) => [201, {
        message: `${body.contacts.length} new contacts added`, created: body.contacts.length, duplicates: 0, failed: 0,
        results: body.contacts.map((c, i) => ({ index: i, status: 'created', contact_id: 20 + i })),
        list: { id: 11, name: 'Acme POS prospects', url: 'http://ext.test/crm/lists/11', campaign_url: 'http://ext.test/crm/campaigns/create?list=11' },
      }],
    },
  });

  await page.getByText('3 businesses found for “restaurants in Ikeja”').waitFor();
  await page.getByText('1 already in your CRM').waitFor();
  assert.equal(await page.getByRole('button', { name: 'Add 2 to CRM' }).isEnabled(), true);
  await shot(page, '06-maps-list');

  await page.getByRole('button', { name: 'Add 2 to CRM' }).click();
  await page.getByText('Create a campaign →').waitFor();
  const post = calls.find((c) => c.key === 'POST /crm/contacts');
  assert.deepEqual(post.body.contacts.map((c) => c.name), ['Mama Put Kitchen', 'Iya Basira Buka']);
  assert.equal(await page.getByRole('button', { name: 'Select businesses to add' }).isDisabled(), true);
  await shot(page, '07-maps-list-added');
  await context.close();
});

test('Maps: a plan without Google Maps shows an upgrade prompt', async () => {
  const { page, context } = await openPopup({ page: pages.place, api: { 'GET /crm/lists': [200, { ...LISTS, can_use_google_maps: false }] } });

  await page.getByText("Adding Google Maps businesses isn't included in your current plan.").waitFor();
  assert.equal(await page.getByRole('button', { name: 'Add to CRM' }).count(), 0);
  await context.close();
});

test('LinkedIn profile: generates a message pack with copy and insert', async () => {
  const { page, calls, context } = await openPopup({
    page: pages.profile,
    api: {
      'POST /linkedin/message': [200, {
        connection_note: 'Hi Ada — love how you grew Mama Put Kitchen to three Ikeja locations. I help Lagos restaurants speed up checkout; would be great to connect.',
        message: 'Thanks for connecting, Ada! Three busy kitchens must make lunch rush interesting. How are you handling orders at peak time today?',
        follow_ups: [
          { send_timing: 'Day 3 if no reply', message: 'Quick thought, Ada — a few Ikeja spots cut queue times by a third with Acme POS. Worth a 2-minute look?' },
          { send_timing: 'Day 7 if no reply', message: "I'll close this out for now — wishing you a great month at the kitchens!" },
        ],
        personalization: 'three Ikeja locations', connection_note_limit: 200, credits_spent: 5, credits_balance: 115,
      }],
    },
  });

  await page.getByText('Ada Obi').first().waitFor();
  assert.match(await page.textContent('#pageContent'), /Founder at Mama Put Kitchen/);
  await shot(page, '08-linkedin-profile');

  await page.getByRole('button', { name: /Generate message/ }).click();
  await page.getByRole('heading', { name: 'Connection note' }).waitFor();
  const body = calls.find((c) => c.key === 'POST /linkedin/message').body;
  assert.equal(body.offer_id, 1);
  assert.equal(body.profile.current_company, 'Mama Put Kitchen');
  assert.match(await page.textContent('#pageContent'), /\d+\/200 characters/);
  assert.equal(await page.textContent('#creditsText'), '115 credits left');
  await shot(page, '09-linkedin-message-generated');

  // No message box open on the profile → a helpful error toast.
  await page.getByRole('button', { name: 'Insert' }).first().click();
  await page.getByText("Open LinkedIn's message box").waitFor();
  await context.close();
});

test('LinkedIn: out of credits shows a top-up prompt', async () => {
  const { page, context } = await openPopup({
    page: pages.profile,
    api: { 'POST /linkedin/message': [402, { message: 'You need 5 credits for this and have 2. Top up or upgrade your plan to keep going.', code: 'insufficient_credits', top_up_url: 'http://ext.test/billing' }] },
  });

  await page.getByRole('button', { name: /Generate message/ }).click();
  await page.getByText('You need 5 credits for this and have 2.').waitFor();
  await page.getByRole('button', { name: 'Top up credits' }).click();
  assert.deepEqual(await page.evaluate(() => window.__opened), ['http://ext.test/billing']);
  await shot(page, '10-out-of-credits');
  await context.close();
});

test('LinkedIn company page', async () => {
  const { page, context } = await openPopup({ page: pages.company });

  await page.getByText('Paystack').first().waitFor();
  assert.match(await page.textContent('#pageContent'), /Financial Services · 201-500 employees/);
  await shot(page, '11-linkedin-company');
  await context.close();
});

test('LinkedIn messaging: suggests a reply and inserts it into the message box', async () => {
  const { page, calls, context } = await openPopup({
    page: pages.messaging,
    api: {
      'POST /linkedin/reply': [200, {
        reply: "Totally fair, Ada — most Ikeja restaurants we work with were burned by pricey POS contracts too. Acme is pay-as-you-go from ₦15k/month with no setup fee. Want me to show you how it handles your lunch rush on a 10-minute call Friday?",
        objection: 'price', next_step: '10-minute call Friday', uses_link: false, credits_spent: 3, credits_balance: 117, draft_id: 4,
      }],
    },
  });

  await page.getByText('Get the best reply to Ada Obi').waitFor();
  assert.match(await page.textContent('#pageContent'), /What does yours cost\?/);
  await shot(page, '12-linkedin-messaging');

  await page.getByRole('button', { name: /Suggest reply/ }).click();
  await page.getByText('Handles: price').waitFor();
  const body = calls.find((c) => c.key === 'POST /linkedin/reply').body;
  assert.deepEqual(body.messages.map((m) => m.role), ['me', 'me', 'them', 'them']);
  assert.equal(body.prospect_name, 'Ada Obi');
  await page.getByRole('button', { name: 'Insert' }).click();
  await page.getByText('Added to the message box').waitFor();
  assert.match((await page.evaluate(() => window.__inserted))[0], /^Totally fair, Ada/);
  await shot(page, '13-linkedin-reply-suggested');
  await context.close();
});

test('Reply helper: paste an email reply and get a response', async () => {
  const { page, calls, context } = await openPopup({
    page: pages.other,
    api: { 'POST /linkedin/reply': [200, { reply: 'Hi Tunde,\n\nGreat question — here is the pricing page: https://acme.example/?ref=nwaeze\n\nHappy to walk you through it.', objection: null, next_step: 'Review pricing', uses_link: true, credits_balance: 117 }] },
  });

  await page.getByRole('tab', { name: 'Reply helper' }).click();
  await page.keyboard.press('Tab');
  await page.getByRole('radio', { name: 'Email' }).focus();
  await page.keyboard.press('ArrowRight');
  assert.equal(await page.getAttribute('[data-channel="whatsapp"]', 'aria-checked'), 'true');
  await page.getByRole('radio', { name: 'Email' }).click();
  await page.fill('#replyMessage', 'Thanks — where can I see pricing?');
  await page.selectOption('#replyOffer', '1');
  await page.getByRole('button', { name: 'Suggest a response' }).click();
  await page.getByText('Includes your link').waitFor();
  const body = calls.find((c) => c.key === 'POST /linkedin/reply').body;
  assert.deepEqual(body, { channel: 'email', their_message: 'Thanks — where can I see pricing?', offer_id: 1 });
  await shot(page, '14-reply-helper');
  await context.close();
});

test('Save page tab keeps the v1.1 capture working', async () => {
  const { page, calls, context } = await openPopup({ page: pages.other, api: { 'POST /clips': [201, { id: 3, message: 'Saved to AffilStack.' }] } });

  await page.getByRole('tab', { name: 'Save page' }).click();
  await page.selectOption('#clipType', 'competitor_ad');
  await page.getByRole('button', { name: 'Save to AffilStack' }).click();
  await page.getByText('Saved to AffilStack.').waitFor();
  assert.deepEqual(calls.find((c) => c.key === 'POST /clips').body, { source_url: 'https://example.com/', title: 'Example', selected_text: null, page_type: 'competitor_ad', offer_id: null });
  await shot(page, '15-save-page');
  await context.close();
});

test('tabs are keyboard accessible', async () => {
  const { page, context } = await openPopup({ page: pages.other });

  await page.getByRole('tab', { name: 'This page' }).focus();
  await page.keyboard.press('ArrowRight');
  assert.equal(await page.getAttribute('#tab-reply', 'aria-selected'), 'true');
  assert.equal(await page.isVisible('#panel-reply'), true);
  await page.keyboard.press('ArrowLeft');
  assert.equal(await page.isVisible('#panel-page'), true);
  await context.close();
});

test('options page verifies and stores the token', async () => {
  const { page, context } = await openPopup({ page: pages.other, token: null, file: 'options.html', viewport: { width: 760, height: 640 } });

  await page.fill('#apiToken', 'aff_new');
  await page.getByRole('button', { name: 'Save & verify' }).click();
  await page.getByText('Connected as nwaeze@example.com.').waitFor();
  assert.equal(await page.evaluate(() => window.__store.apiToken), 'aff_new');
  await shot(page, '16-options-connected');
  await context.close();
});

test('options page shows a red status with a fix link for a rejected token', async () => {
  const { page, context } = await openPopup({ page: pages.other, token: null, file: 'options.html', viewport: { width: 760, height: 640 }, api: { 'GET /me': [401, { message: 'Invalid' }] } });

  await page.fill('#apiToken', 'aff_bad');
  await page.getByRole('button', { name: 'Save & verify' }).click();
  await page.getByText('That token was rejected').waitFor();
  assert.equal(await page.getAttribute('#status a', 'href'), 'http://ext.test/extension');
  assert.equal(await page.evaluate(() => window.__store.apiToken), undefined);
  await shot(page, '17-options-rejected');
  await context.close();
});
