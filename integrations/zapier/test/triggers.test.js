'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const newOfferResearchCompleted = require('../triggers/newOfferResearchCompleted');
const newCrmContact = require('../triggers/newCrmContact');

function fakeZ(responses) {
  const calls = [];
  return {
    calls,
    request: async (options) => {
      calls.push(options);
      const match = responses.shift();
      return { status: 200, data: match };
    },
  };
}

test('newOfferResearchCompleted.performSubscribe posts the Zap target URL and the right event name', async () => {
  const z = fakeZ([{ id: 'sub-1' }]);
  const bundle = { authData: { apiUrl: 'https://example.test/api/v1' }, targetUrl: 'https://hooks.zapier.com/x' };

  const result = await newOfferResearchCompleted.operation.performSubscribe(z, bundle);

  assert.deepEqual(result, { id: 'sub-1' });
  assert.equal(z.calls[0].method, 'POST');
  assert.equal(z.calls[0].body.event, 'generation.completed');
  assert.equal(z.calls[0].body.target_url, bundle.targetUrl);
});

test('newOfferResearchCompleted.perform only fires for the research module', () => {
  const researchEvent = { event: 'generation.completed', data: { generation_id: 1, module: 'research', offer_id: 5 }, delivery_id: 9 };
  const blogEvent = { event: 'generation.completed', data: { generation_id: 2, module: 'blog_article', offer_id: 5 }, delivery_id: 10 };

  const researchResult = newOfferResearchCompleted.operation.perform(null, { cleanedRequest: researchEvent });
  const blogResult = newOfferResearchCompleted.operation.perform(null, { cleanedRequest: blogEvent });

  assert.equal(researchResult.length, 1);
  assert.equal(researchResult[0].offer_id, 5);
  assert.deepEqual(blogResult, []);
});

test('newOfferResearchCompleted.performUnsubscribe deletes the stored subscription id', async () => {
  const z = fakeZ([{ id: 'sub-1' }]);
  const bundle = { authData: { apiUrl: 'https://example.test/api/v1' }, subscribeData: { id: 'sub-1' } };

  await newOfferResearchCompleted.operation.performUnsubscribe(z, bundle);

  assert.equal(z.calls[0].method, 'DELETE');
  assert.ok(z.calls[0].url.includes('sub-1'));
});

test('newCrmContact.perform passes the contact payload through', () => {
  const event = { event: 'crm_contact.created', data: { contact_id: 7, name: 'Jane' }, delivery_id: 3 };

  const result = newCrmContact.operation.perform(null, { cleanedRequest: event });

  assert.equal(result[0].contact_id, 7);
  assert.equal(result[0].name, 'Jane');
});
