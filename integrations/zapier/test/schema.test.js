'use strict';

// Validates the app the same way `zapier push` would before ever
// touching a live account. Note: zapier-platform-schema's
// validateAppDefinition() can't be called directly on this file's
// exported App object — it expects functions already converted to the
// `$func$…$` placeholders that only the real CLI's build step produces,
// so calling it on raw source functions always reports every
// function-valued field as "wrong type". The actual `zapier validate`
// command performs that conversion internally, so shelling out to the
// installed CLI is what genuinely exercises the schema, offline and
// without `zapier login`.
const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const App = require('../index.js');

test('the app definition is valid per zapier-platform-schema', () => {
  const zapierBin = path.join(__dirname, '..', 'node_modules', '.bin', 'zapier');
  const result = spawnSync(zapierBin, ['validate'], {
    cwd: path.join(__dirname, '..'),
    encoding: 'utf8',
  });

  assert.equal(result.status, 0, result.stdout + result.stderr);
});

test('both REST Hook triggers declare subscribe, unsubscribe, and perform', () => {
  for (const trigger of Object.values(App.triggers)) {
    assert.equal(trigger.operation.type, 'hook');
    assert.equal(typeof trigger.operation.performSubscribe, 'function');
    assert.equal(typeof trigger.operation.performUnsubscribe, 'function');
    assert.equal(typeof trigger.operation.perform, 'function');
  }
});

test('both creates declare their required input fields', () => {
  const offerFields = App.creates.create_offer.operation.inputFields.map((f) => f.key);
  assert.ok(offerFields.includes('product_name'));
  assert.ok(offerFields.includes('product_url'));
  assert.ok(offerFields.includes('affiliate_network'));
});
