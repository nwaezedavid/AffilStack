'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const { AffilStackClient, AffilStackApiError } = require('../index.js');

function fakeFetch(responses) {
  const calls = [];
  const fn = async (url, init) => {
    calls.push({ url: url.toString(), init });
    const match = responses.shift();
    if (!match) {
      throw new Error(`Unexpected fetch call: ${url}`);
    }
    return {
      ok: match.status >= 200 && match.status < 300,
      status: match.status,
      text: async () => JSON.stringify(match.body),
    };
  };
  fn.calls = calls;
  return fn;
}

test('me() sends a GET with the bearer token', async () => {
  const client = new AffilStackClient('aff_test123', { baseUrl: 'https://example.test/api/v1' });
  global.fetch = fakeFetch([{ status: 200, body: { id: 1, name: 'Jordan' } }]);

  const result = await client.me();

  assert.deepEqual(result, { id: 1, name: 'Jordan' });
  assert.equal(global.fetch.calls[0].url, 'https://example.test/api/v1/me');
  assert.equal(global.fetch.calls[0].init.method, 'GET');
  assert.equal(global.fetch.calls[0].init.headers.Authorization, 'Bearer aff_test123');
});

test('createOffer() sends a POST with a JSON body and Idempotency-Key', async () => {
  const client = new AffilStackClient('aff_test123', { baseUrl: 'https://example.test/api/v1' });
  global.fetch = fakeFetch([{ status: 201, body: { id: 42, status: 'queued' } }]);

  const offer = await client.createOffer(
    { product_name: 'Widget', product_url: 'https://a.example', affiliate_network: 'ShareASale' },
    { idempotencyKey: 'key-1' }
  );

  assert.equal(offer.id, 42);
  const call = global.fetch.calls[0];
  assert.equal(call.init.method, 'POST');
  assert.equal(call.init.headers['Idempotency-Key'], 'key-1');
  assert.equal(call.init.headers['Content-Type'], 'application/json');
  assert.deepEqual(JSON.parse(call.init.body), {
    product_name: 'Widget',
    product_url: 'https://a.example',
    affiliate_network: 'ShareASale',
  });
});

test('listOffers() forwards per_page as a query parameter', async () => {
  const client = new AffilStackClient('aff_test123', { baseUrl: 'https://example.test/api/v1' });
  global.fetch = fakeFetch([{ status: 200, body: { data: [] } }]);

  await client.listOffers({ perPage: 10 });

  assert.equal(global.fetch.calls[0].url, 'https://example.test/api/v1/offers?per_page=10');
});

test('bulkCreateCrmContacts() posts the array under a "contacts" key', async () => {
  const client = new AffilStackClient('aff_test123', { baseUrl: 'https://example.test/api/v1' });
  global.fetch = fakeFetch([{ status: 200, body: { created: [{ id: 1 }], failed: [] } }]);

  const result = await client.bulkCreateCrmContacts([{ name: 'A' }, { name: 'B' }]);

  assert.equal(result.created.length, 1);
  const call = global.fetch.calls[0];
  assert.equal(call.url, 'https://example.test/api/v1/crm-contacts/bulk');
  assert.deepEqual(JSON.parse(call.init.body), { contacts: [{ name: 'A' }, { name: 'B' }] });
});

test('a non-2xx response throws AffilStackApiError with the parsed body', async () => {
  const client = new AffilStackClient('aff_test123', { baseUrl: 'https://example.test/api/v1' });
  global.fetch = fakeFetch([{ status: 402, body: { message: 'Insufficient API wallet balance for this request.' } }]);

  await assert.rejects(
    () => client.createOffer({ product_name: 'A', product_url: 'https://a.example', affiliate_network: 'ShareASale' }),
    (err) => {
      assert.ok(err instanceof AffilStackApiError);
      assert.equal(err.status, 402);
      assert.equal(err.message, 'Insufficient API wallet balance for this request.');
      return true;
    }
  );
});

test('constructing without an API key throws immediately', () => {
  assert.throws(() => new AffilStackClient(''));
});
