// Tests for resources/browser-extension/extractor.js — the page reader the
// popup injects into the current tab. Runs the real extractor inside Chromium
// against HTML fixtures modelled on Google Maps / LinkedIn markup (see the
// comment at the top of each fixture; the live sites can't be reached from
// CI, so the fixtures stand in for them).
//
// Run (Playwright isn't a project dependency — point NODE_PATH at an install):
//   NODE_PATH=/tmp/claude-0/pw/node_modules node --test tests/browser-extension/*.test.cjs
// Set CHROMIUM_PATH to use a specific Chromium (default /opt/pw-browsers/chromium).
const { test, before, after } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '../..');
const extractorPath = path.join(root, 'resources/browser-extension/extractor.js');
const fixture = (name) => fs.readFileSync(path.join(__dirname, 'fixtures', name), 'utf8');

let browser;
let page;

before(async () => {
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium' });
  page = await browser.newPage();
});

after(async () => {
  await browser.close();
});

async function extract(html, url) {
  await page.setContent(html);
  await page.addScriptTag({ path: extractorPath });
  return page.evaluate((u) => globalThis.__affilstackExtractor.extract({ url: u }), url);
}

test('reads a Google Maps place page', async () => {
  const result = await extract(
    fixture('maps-place.html'),
    'https://www.google.com/maps/place/Mama+Put+Kitchen/@6.6018,3.3515,17z/data=!3m1!4b1!4m6!3m5!1s0x103b93a1d2f4c5e7:0x9d7c4e2b1a3f5c68!8m2!3d6.6018!4d3.3515!16s%2Fg%2F11c1abcd!19sChIJ5cX00qGTOxARaFw-K0eTn0?entry=ttu',
  );

  assert.equal(result.kind, 'maps_place');
  const place = result.data;
  assert.equal(place.name, 'Mama Put Kitchen');
  assert.equal(place.category, 'Nigerian restaurant');
  assert.equal(place.address, '12 Allen Avenue, Ikeja, Lagos 101233, Nigeria');
  assert.equal(place.phone, '0803 123 4567');
  assert.equal(place.website, 'https://www.mamaputkitchen.ng/?utm_source=gmb');
  assert.equal(place.rating, 4.6);
  assert.equal(place.review_count, 1284);
  assert.equal(place.place_id, 'ChIJ5cX00qGTOxARaFw-K0eTn0');
  assert.equal(place.ftid, '0x103b93a1d2f4c5e7:0x9d7c4e2b1a3f5c68');
  assert.equal(place.cid, BigInt('0x9d7c4e2b1a3f5c68').toString());
  assert.equal(place.maps_url, 'https://www.google.com/maps/place/?q=place_id:ChIJ5cX00qGTOxARaFw-K0eTn0');
});

test('a place page with only a name still works and never throws', async () => {
  const result = await extract(
    '<div role="main" aria-label="Corner Shop"><h1 class="DUwDvf">Corner Shop</h1><button data-item-id="address" aria-label="Address: 1 Main St"></button></div>',
    'https://www.google.com/maps/place/Corner+Shop/@1,1,17z',
  );

  assert.equal(result.kind, 'maps_place');
  assert.equal(result.data.name, 'Corner Shop');
  assert.equal(result.data.address, '1 Main St');
  assert.equal(result.data.phone, '');
  assert.equal(result.data.website, '');
  assert.equal(result.data.rating, null);
  assert.equal(result.data.place_id, '');
});

test('reads every business on a Google Maps results list', async () => {
  const result = await extract(fixture('maps-list.html'), 'https://www.google.com/maps/search/restaurants+in+Ikeja/@6.60,3.35,14z');

  assert.equal(result.kind, 'maps_list');
  assert.equal(result.data.query, 'restaurants in Ikeja');
  assert.equal(result.data.items.length, 3);

  const [mama, bukka, iya] = result.data.items;
  assert.equal(mama.name, 'Mama Put Kitchen');
  assert.equal(mama.category, 'Nigerian restaurant');
  assert.equal(mama.address, '12 Allen Avenue');
  assert.equal(mama.phone, '0803 123 4567');
  assert.equal(mama.website, 'https://www.mamaputkitchen.ng/');
  assert.equal(mama.rating, 4.6);
  assert.equal(mama.review_count, 1284);
  assert.equal(mama.place_id, 'ChIJ5cX00qGTOxARaFw-K0eTn0');

  assert.equal(bukka.category, 'Fast food restaurant');
  assert.equal(bukka.address, '40 Allen Ave');
  assert.equal(bukka.phone, '+234 1 555 0000');
  assert.equal(bukka.website, '');
  assert.equal(bukka.review_count, 532);

  assert.equal(iya.name, 'Iya Basira Buka');
  assert.equal(iya.category, 'Restaurant');
  assert.equal(iya.rating, null);
  assert.equal(iya.place_id, '');
  assert.equal(iya.cid, '1234');
});

test('an empty Maps page is reported as such', async () => {
  const result = await extract('<div role="main"></div>', 'https://www.google.com/maps/@6.5,3.3,12z');
  assert.equal(result.kind, 'maps_empty');
});

test('reads a LinkedIn profile', async () => {
  const result = await extract(fixture('linkedin-profile.html'), 'https://www.linkedin.com/in/ada-obi-12345/?originalSubdomain=ng');

  assert.equal(result.kind, 'linkedin_profile');
  const profile = result.data;
  assert.equal(profile.type, 'person');
  assert.equal(profile.name, 'Ada Obi');
  assert.equal(profile.url, 'https://www.linkedin.com/in/ada-obi-12345/');
  assert.equal(profile.headline, 'Founder at Mama Put Kitchen | Feeding Lagos one plate at a time');
  assert.equal(profile.location, 'Lagos, Lagos State, Nigeria');
  assert.equal(profile.current_company, 'Mama Put Kitchen');
  assert.equal(profile.current_role, 'Founder & CEO');
  assert.match(profile.about, /^I run three busy restaurants in Ikeja/);
  assert.equal(profile.experience.length, 2);
  assert.equal(profile.experience[0], 'Founder & CEO · Mama Put Kitchen · Full-time · Mar 2019 - Present · 7 yrs 7 mos · Ikeja, Lagos');
  assert.equal(profile.experience[1], 'Operations Manager · Chicken Republic · Full-time · 2014 - 2019 · 5 yrs');
  assert.equal(result.composer, false);
});

test('a bare profile falls back to the page title for the name', async () => {
  const result = await extract('<title>(2) Chidi Okeke | LinkedIn</title><main><div>Loading…</div></main>', 'https://www.linkedin.com/in/chidi/');

  assert.equal(result.kind, 'linkedin_profile');
  assert.equal(result.data.name, 'Chidi Okeke');
  assert.deepEqual(result.data.experience, []);
});

test('reads a LinkedIn company page', async () => {
  const result = await extract(fixture('linkedin-company.html'), 'https://www.linkedin.com/company/paystack/about/');

  assert.equal(result.kind, 'linkedin_company');
  const company = result.data;
  assert.equal(company.type, 'company');
  assert.equal(company.name, 'Paystack');
  assert.equal(company.url, 'https://www.linkedin.com/company/paystack/');
  assert.equal(company.headline, 'Modern online and offline payments for Africa');
  assert.equal(company.industry, 'Financial Services');
  assert.equal(company.company_size, '201-500 employees');
  assert.equal(company.location, 'Lagos, Lagos');
  assert.equal(company.followers, '412K followers');
  assert.equal(company.website, 'https://paystack.com');
  assert.match(company.company_description, /^Paystack helps businesses in Africa/);
});

test('reads a LinkedIn conversation with sender roles', async () => {
  const result = await extract(fixture('linkedin-messaging.html'), 'https://www.linkedin.com/messaging/thread/2-ZmFrZS10aHJlYWQ=/');

  assert.equal(result.kind, 'linkedin_messaging');
  assert.equal(result.composer, true);
  assert.equal(result.data.participant, 'Ada Obi');
  assert.equal(result.data.me, 'Nwaeze David');
  assert.deepEqual(result.data.messages.map((m) => m.role), ['me', 'me', 'them', 'them']);
  assert.equal(result.data.messages[1].sender, 'Nwaeze David');
  assert.equal(result.data.messages[3].sender, 'Ada Obi');
  assert.equal(result.data.messages[3].text, 'What does yours cost?');
});

test('roles still work when LinkedIn drops the --other class', async () => {
  const html = fixture('linkedin-messaging.html').replace(/ msg-s-event-listitem--other/g, '');
  const result = await extract(html, 'https://www.linkedin.com/messaging/thread/x/');

  assert.deepEqual(result.data.messages.map((m) => m.role), ['me', 'me', 'them', 'them']);
});

test('inserts a suggested reply into the open message box', async () => {
  await extract(fixture('linkedin-messaging.html'), 'https://www.linkedin.com/messaging/thread/x/');
  const outcome = await page.evaluate(() => {
    let inputs = 0;
    document.querySelector('.msg-form__contenteditable').addEventListener('input', () => { inputs++; });
    const result = globalThis.__affilstackExtractor.insertText('Totally fair, Ada.\n\nWant a quick demo?');
    return { result, inputs, html: document.querySelector('.msg-form__contenteditable').innerHTML };
  });

  assert.deepEqual(outcome.result, { ok: true, target: 'message' });
  assert.equal(outcome.inputs, 1);
  assert.equal(outcome.html, '<p>Totally fair, Ada.</p><p><br></p><p>Want a quick demo?</p>');
});

test('inserts a connection note into LinkedIn\'s invite dialog first', async () => {
  await extract(
    fixture('linkedin-profile.html').replace('</main>', '</main><div role="dialog"><textarea id="custom-message" name="message" maxlength="200"></textarea></div>'),
    'https://www.linkedin.com/in/ada-obi-12345/',
  );
  const outcome = await page.evaluate(() => ({
    result: globalThis.__affilstackExtractor.insertText('Hi Ada — would love to connect.'),
    value: document.querySelector('#custom-message').value,
  }));

  assert.deepEqual(outcome.result, { ok: true, target: 'note' });
  assert.equal(outcome.value, 'Hi Ada — would love to connect.');
});

test('insert reports when no message box is open', async () => {
  await extract(fixture('linkedin-company.html'), 'https://www.linkedin.com/company/paystack/');
  const result = await page.evaluate(() => globalThis.__affilstackExtractor.insertText('Hello'));
  assert.deepEqual(result, { ok: false, reason: 'no_composer' });
});

test('any other site is "other"', async () => {
  const result = await extract('<h1>Shop</h1>', 'https://example.com/product');
  assert.equal(result.kind, 'other');
});
