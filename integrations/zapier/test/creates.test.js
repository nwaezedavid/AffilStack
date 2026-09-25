'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const createOffer = require('../creates/createOffer');
const createCrmContact = require('../creates/createCrmContact');

function fakeZ(response) {
  const calls = [];
  return {
    calls,
    request: async (options) => {
      calls.push(options);
      return { status: 201, data: response };
    },
  };
}

test('createOffer.perform posts the mapped fields to /offers', async () => {
  const z = fakeZ({ id: 55, status: 'queued' });
  const bundle = {
    authData: { apiUrl: 'https://example.test/api/v1' },
    inputData: { product_name: 'Widget', product_url: 'https://a.example', affiliate_network: 'ShareASale' },
    meta: {},
  };

  const result = await createOffer.operation.perform(z, bundle);

  assert.equal(result.id, 55);
  assert.equal(z.calls[0].method, 'POST');
  assert.equal(z.calls[0].body.product_name, 'Widget');
});

test('createOffer.perform sets an Idempotency-Key derived from the Zap run', async () => {
  const z = fakeZ({ id: 55 });
  const bundle = {
    authData: { apiUrl: 'https://example.test/api/v1' },
    inputData: { product_name: 'Widget', product_url: 'https://a.example', affiliate_network: 'ShareASale' },
    meta: { zap: { id: 42 }, limit: 'run-1' },
  };

  await createOffer.operation.perform(z, bundle);

  assert.ok(z.calls[0].headers['Idempotency-Key'].includes('42'));
});

test('createCrmContact.perform posts the mapped fields to /crm-contacts', async () => {
  const z = fakeZ({ id: 7, name: 'Jane Doe' });
  const bundle = {
    authData: { apiUrl: 'https://example.test/api/v1' },
    inputData: { name: 'Jane Doe', email: 'jane@example.com' },
  };

  const result = await createCrmContact.operation.perform(z, bundle);

  assert.equal(result.id, 7);
  assert.equal(z.calls[0].url, 'https://example.test/api/v1/crm-contacts');
  assert.equal(z.calls[0].body.email, 'jane@example.com');
});
