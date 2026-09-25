# affilstack-sdk

Official JavaScript/TypeScript client for the [AffilStack API](../../resources/openapi/openapi.yaml).

Not yet published to npm — this package is ready to publish (`npm publish`) whenever
AffilStack decides to, or you can use it straight from this path/a git dependency in the
meantime.

## Install (once published)

```bash
npm install affilstack-sdk
```

## Usage

```js
const { AffilStackClient, AffilStackApiError } = require('affilstack-sdk');

const client = new AffilStackClient('aff_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', {
  baseUrl: 'https://your-affilstack-domain.example/api/v1',
});

async function main() {
  const me = await client.me();
  console.log(`Wallet balance: $${(me.api_wallet_balance_cents / 100).toFixed(2)}`);

  try {
    const offer = await client.createOffer(
      {
        product_name: 'Acme Widget',
        product_url: 'https://acme.example/widget',
        affiliate_network: 'ShareASale',
      },
      { idempotencyKey: 'acme-widget-2026-09-25' } // safe to retry with the same key
    );
    console.log('Created offer', offer.id);
  } catch (err) {
    if (err instanceof AffilStackApiError && err.status === 402) {
      console.error('Not enough API wallet balance — top up from the dashboard.');
    } else {
      throw err;
    }
  }

  const { created, failed } = await client.bulkCreateCrmContacts([
    { name: 'Jane Doe', email: 'jane@example.com' },
    { name: 'John Doe', email: 'john@example.com' },
  ]);
  console.log(`${created.length} contacts created, ${failed.length} failed`);
}

main();
```

## Sandbox mode

Pass a token created in sandbox mode (the API Access page's "Sandbox token" checkbox) to
develop against fake data with zero cost — see the `token.is_sandbox` field on `me()`.

## Testing this package

```bash
npm test
```
