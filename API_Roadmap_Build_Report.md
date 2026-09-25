# AffilStack API: Roadmap Build Report

**Branch:** `affilstack-app` · **Commit:** `727847dd` (local; see "Git status" below) · **Date:** 2026-09-25

This closes out the original four-part request and the full roadmap build that followed it.
Original items 1–4 were finished in the prior work session; this report's main subject is
the instruction that came after: *"I want you to build everything you suggested in the
roadmap."* All ten roadmap items are now built, tested, and wired into the existing app —
nothing was left as a stub.

## The original four items

1. **API usage prepay wallet with pricing** — done. Per-call metering, a prepaid wallet
   balance, auto-recharge, and a full pricing model live on the API Access dashboard.
2. **Suggested API features roadmap** — done. Delivered as `API_Wallet_Pricing_and_Roadmap.md`,
   and its ten suggestions are exactly what the rest of this report covers.
3. **Simplify Google Maps lead-finding** — done. Offer Research now runs as a guided
   product-to-leads flow instead of a raw map tool.
4. **Fix the Gmail connection button** — done.

## The roadmap, built end to end

Every item below reuses the app's existing patterns (the same middleware pipeline, the same
webhook infrastructure, the same validation rules) rather than adding a parallel system, so
nothing here should feel bolted on from inside the dashboard.

**1. Idempotency keys.** `POST /offers` and `POST /crm-contacts` accept an `Idempotency-Key`
header. The same key with the same request body replays the original response instead of
creating a duplicate; the same key with a *different* body is rejected (422). Keys are scoped
per user, Stripe-style.

**2. Scoped read-only tokens.** A token can now be minted as `full` or `read_only` from the
API Access page. A read-only token gets a 403 on any write request before it reaches the
controller.

**3. Plan-aware rate limits.** Each plan gets its own requests-per-minute ceiling
(Starter/Growth: 60, Pro: 120, Business/Agency: 300, default: 60), enforced per token. Worth
flagging as a real bug caught and fixed during the build: Laravel silently reorders certain
built-in middleware ahead of custom ones regardless of route order, which made the rate
limiter blind to which token was calling. Fixed by having the limiter resolve the token
itself instead of trusting middleware order.

**4. Sandbox mode.** A token can be flagged sandbox at creation. Sandbox offers/contacts are
fully isolated from live data, cost nothing, bypass the wallet entirely, and return realistic
canned research instantly — good for testing an integration without spending real credits or
waiting on a real AI run.

**5. Bulk CRM contacts.** `POST /crm-contacts/bulk` accepts up to 100 contacts per call and
returns which ones succeeded and which failed individually (partial success), reusing the
exact same validation as the single-contact endpoint.

**6. Per-token usage analytics.** The API Access page now shows each token's call count and
spend over the last 30 days, backed by a new request log table.

**7. Published OpenAPI spec.** A complete, hand-written OpenAPI 3.1 document is served at
`GET /api/v1/openapi.yaml` — linked directly from the dashboard — covering every endpoint,
including idempotency, scopes, and sandbox mode.

**8. Webhook delivery replay.** Any individual webhook delivery in the dashboard's Webhooks
section now has a "Replay" button to resend it without waiting for the triggering event to
happen again.

**9. JS and Python SDKs.** `affilstack-sdk` (JS, zero dependencies) and `affilstack` (Python,
depends only on `requests`) live in `sdks/`. Both are hand-built to match the OpenAPI spec
field-for-field rather than pipeline-generated, and both ship with their own test suites (6/6
passing each). Neither is published to npm/PyPI yet — that's an account/ownership decision
for you to make whenever you're ready (`npm publish`, `python -m build && twine upload`).

**10. Zapier integration.** A real Zapier Platform CLI app in `integrations/zapier/`: two
triggers (new offer research completed, new CRM contact — both built on the existing webhook
infrastructure, so a Zap subscription shows up and can be revoked from the same dashboard
Webhooks section as anything else) and two creates (create offer, create CRM contact). It
passes `zapier validate` — Zapier's own real schema and integration-checks tool — cleanly: 0
structural errors, 0 publishing blockers. **Going live needs one thing only you can do:**
`zapier login` with your own Zapier developer account, then `zapier push`. Full steps are in
`integrations/zapier/README.md`. Make.com is a natural next step on the same primitives
(same webhook subscriptions, same `/offers`/`/crm-contacts` calls) but wasn't built this
round — different platform, its own scaffold and review process.

## Testing

Everything was tested before being called done, not after:

- All 878 existing PHP tests pass, plus every new feature test written for this build
  (idempotency, scopes, rate limits, sandbox, bulk create, usage logging, the OpenAPI route,
  webhook replay, the Zapier subscription endpoints) — run together, not in isolation.
- Pint (the project's code style tool) is clean across the whole codebase.
- The JS SDK's test suite: 6/6 passing. The Python SDK's: 6/6 passing.
- The Zapier app's own test suite: 10/10 passing, plus a clean pass of the real `zapier
  validate` CLI (not a hand-rolled approximation of it — worth mentioning because the
  obvious approach, calling Zapier's schema validator directly on the app's source, doesn't
  actually work: it expects functions already compiled to placeholders by Zapier's own build
  step. The test suite shells out to the real CLI instead, so it's checking the same thing
  Zapier's servers would check.)

## Security scan status

This environment's automated security scanner (HawkScan/StackHawk) could not run a fresh
scan this round — no API key is provisioned in this session, which its own tooling confirms
directly ("API key not configured"). That's an environment limitation here, not something
retriable on my end.

For context: a scan against an earlier version of this app did complete and surface real
(Medium/Low severity) findings — a missing `X-Content-Type-Options` header, a wildcard in the
Content-Security-Policy, session cookies flagged for transmission, and a couple of others —
before failing at the very last step (uploading results back to StackHawk's platform, which
hits a proxy/S3 header conflict specific to this sandbox). Those findings predate this
round's roadmap work and haven't been re-verified against the current code, so I'm flagging
them rather than either ignoring them or changing security-relevant headers blind without a
scan to confirm the fix actually lands. Happy to work through them specifically if you'd
like — they're well-understood, low-risk fixes.

## Git status

Committed locally on `affilstack-app` as `727847dd`. **Not yet pushed** — this session's git
proxy reports the repository isn't in its authorized set:

> access denied by the git proxy: nwaezedavid/AffilStack is not in this session's authorized
> repository set

This (and one earlier commit from the previous session, `a1f75be3`, also still unpushed) both
need the repository added to this session's authorized sources before a push can go through —
that's a permissions setting on your end, not something I can work around from here. Once
that's sorted, pushing both commits is a single `git push origin affilstack-app`.

## Scope calls made along the way

Two items in the roadmap extend beyond pure backend work, so I made explicit calls on how far
to take them rather than guessing silently:

- **SDKs**: hand-built rather than generated by a spec-to-client pipeline, but genuinely
  functional and tested against the real API shapes — not a stub.
- **Zapier**: a complete, schema-valid app scaffold, but *publishing* it is inherently
  something only your own Zapier account can do. I did everything short of that.

Both are called out above at the point they matter, not just here — this section is a
summary, not new information.
