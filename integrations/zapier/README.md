# AffilStack for Zapier

A [Zapier Platform CLI](https://github.com/zapier/zapier-platform) app for AffilStack
(API roadmap item #9). It wraps the [AffilStack API](../../resources/openapi/openapi.yaml)
so a user's Zaps can react to AffilStack events and create records in AffilStack, without
either side writing custom integration code.

Authentication is a pasted AffilStack API token (custom auth) — the same kind of token
minted from the dashboard's **API Access** page and used by the JS/Python SDKs
(`../../sdks`). A read-only or sandbox token both work here, subject to their usual limits
(a read-only token can use the triggers but not the creates; a sandbox token's creates
return instant canned data and never touch billing).

## What it does

**Triggers** (REST Hooks — instant, not polled):

- **New Offer Research Completed** — fires when an offer's research finishes. Subscribes
  to AffilStack's existing `generation.completed` webhook event and filters client-side to
  the `research` module, since there's no separate "offer ready" event on the backend (see
  `app/Models/Generation.php`'s `booted()` hook) — an offer's research finishing *is* that
  Generation completing.
- **New CRM Contact** — fires when a contact is created from any AffilStack entry point
  (API, Lead Finder, or the dashboard's own CRM form). Subscribes to `crm_contact.created`.

**Creates:**

- **Create Offer** — `POST /offers`. Metered per the plan's per-call cost on a live token;
  free and instant on a sandbox token. Attaches an `Idempotency-Key` derived from the Zap's
  own run ID, so Zapier's automatic retry-on-timeout can't create a duplicate offer.
- **Create CRM Contact** — `POST /crm-contacts`. Free.

Subscribing a trigger creates a normal row in AffilStack's own `webhook_endpoints` table via
two small new endpoints, `POST /v1/zapier/subscriptions` and
`DELETE /v1/zapier/subscriptions/{id}` (`app/Http/Controllers/Api/V1/ZapierSubscriptionsController.php`) —
there's no separate subsystem for Zapier. That means a user's Zaps show up, and can be
individually revoked, from the same dashboard **API Access → Webhooks** section as any other
webhook endpoint, including delivery history and the roadmap's "Replay" action.

## What's here

```
authentication.js              custom API-key auth (tests GET /me)
middleware/includeApiKey.js    attaches the bearer token to every request
triggers/newOfferResearchCompleted.js
triggers/newCrmContact.js
creates/createOffer.js
creates/createCrmContact.js
index.js                       assembles the App definition
test/                          node:test suite (see below)
```

## Local development

```bash
npm install
npm test        # node:test — unit tests for each trigger/create's request-building logic
npm run validate # the real `zapier validate` CLI — full schema + integration-checks pass
```

Both currently pass clean: `npm test` is 10/10, and `npm run validate` reports the
integration as structurally sound with 0 errors and 0 publishing-blocking issues (only a
handful of non-blocking advisory warnings — e.g. suggesting a direct doc link for the API
key field, which only matters if this is ever submitted to Zapier's public App Directory).

One non-obvious thing `npm run validate` caught during development: `zapier-platform-schema`'s
`validateAppDefinition()` can't be called directly on this file's plain JS — it expects
functions already converted to the `$func$…$` placeholders that only the real CLI's build
step produces. `test/schema.test.js` shells out to the installed `zapier` CLI itself rather
than reimplementing that conversion, so the test is exercising exactly what `zapier push`
would run.

## Going live

This scaffold is complete and tested, but *using* it from an actual Zap requires an AffilStack
account of your own on Zapier's platform — that's an account-ownership step only you can take,
not something that can be finished from inside this repo:

```bash
npx zapier login              # opens a browser to your own Zapier developer account
npx zapier register "AffilStack"   # first time only — creates the app on your account
npx zapier push                # uploads this version as a private integration
```

After `push`, the integration is usable immediately in Zaps under your own Zapier account
(invite teammates as testers from the Zapier dashboard to share it before it's public).
Submitting it to Zapier's public App Directory is a separate, later step with its own
requirements (support docs, a listed icon, Zapier's review) — nothing here blocks that path,
but it's an intentional choice to make once the integration has real usage, not a default.

**Make.com** is a natural next integration using the same primitives this app already
exposes — its own webhook subscription model and HTTP module map onto the same
`/v1/zapier/subscriptions`-style endpoints and `/offers` / `/crm-contacts` calls. It wasn't
built this round since it's a distinct platform with its own scaffold and review process,
but nothing about this design is Zapier-specific enough to block it.
