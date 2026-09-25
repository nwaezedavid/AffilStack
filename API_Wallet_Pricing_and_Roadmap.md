# AffilStack API: Pricing and Roadmap Recommendation

## What's live today

The API usage prepay wallet is built and shipped. Every user now has a wallet balance (`api_wallet_balance_cents`) that is completely separate from their plan's monthly credit allowance — it exists purely to pay for calling the API directly, and it starts at $0.

Right now the wallet does exactly one thing: it charges **$0.75** every time a call is made to `POST /offers` (the endpoint that queues AI offer research), which is currently the only API action that costs AffilStack real money to serve. Every read endpoint — fetching offers, generated content, CRM contacts, referral summaries — stays free, and that will likely remain true going forward: metering should track cost-to-serve, not general API traffic.

Users top up the wallet from the API Access page in the dashboard, choosing a preset amount ($10, $25, or $100) or any amount from $10 upward, and paying through whichever gateway they'd use elsewhere on the platform (Stripe, Flutterwave, Paystack, or PayPal). Paying with a card automatically saves it, exactly the way every other purchase on AffilStack already works — there is no separate "add a card" screen. Once a card is on file, a user can turn on auto-recharge: pick a threshold ("recharge when my balance drops below $X") and an amount ("add $Y each time"), and the wallet tops itself up in the background the moment a metered call would otherwise be blocked. If a call is billed and then fails for an unrelated reason (say, the user is out of dashboard credits, which is checked separately), the $0.75 is refunded automatically — nobody pays for a request that didn't do the work it was billed for. PayPal is the one exception: because AffilStack doesn't have PayPal's vault/reusable-billing integration, a PayPal-funded account can top up manually but can't be selected for auto-recharge — the dashboard makes that clear rather than offering a card option that would quietly fail every time.

This mirrors the architecture Twilio, OpenAI, and Anthropic all use for their own developer APIs: a prepaid balance that is entirely separate from any subscription tier, refillable on demand or automatically, with usage debited as it happens. That's a deliberate choice — it's the model developers already expect from an API product, as distinct from a subscription's bundled usage.

## Pricing: where $0.75 comes from, and whether to adjust it

A dashboard-triggered offer research costs 5 credits internally (`config('credits.costs.research')`). AffilStack's own credit top-up packages sell credits at $0.073–$0.095 each depending on package size, which puts the "dashboard-equivalent" cost of one research run at roughly **$0.37–$0.48**. The $0.75 API price is a premium of **roughly 55–100% over that blended rate** (about 80% at the midpoint) — the reasoning being that API access carries no subscription commitment behind it, and has to independently absorb card-processing fees that a subscription amortizes across many actions.

That said, it's worth being direct about where this sits versus the market: Zapier, which faces the most similar problem (metering task-based automation on top of subscription tiers), prices pay-per-task overage at a flat **25% premium** over the in-plan rate — well below AffilStack's current ~80%. Postmark takes a different approach entirely: its per-email overage rate actually gets *cheaper* on higher subscription tiers ($1.80/1,000 emails on its $15/mo plan down to $1.20/1,000 on its top tier), using the API/overage price as a retention lever rather than a flat fee.

Two honest options, not a single right answer:

**Keep $0.75 as-is.** It's a clean, round, easy-to-explain number, and pay-as-you-go API access genuinely is a different (more convenient, zero-commitment) product than a subscription — charging more for that convenience is defensible, and it's a single config value (`config('api_billing.costs')`) that costs nothing to change later if usage data says otherwise.

**Move toward Zapier's convention.** Dropping to something like $0.50–$0.55 (a 20–30% premium over the dashboard-equivalent cost) would put AffilStack closer to how the most comparable competitor prices the same problem, at the cost of some margin.

If a middle path is wanted, the Postmark model is worth adopting as a phase-two refinement regardless of where the base price lands: charge Starter and Growth subscribers the full API rate, but discount it for Pro, Business, and Agency subscribers (for example, $0.75 / $0.60 / $0.50 by tier). That rewards exactly the customers already paying the most, and it's a small change to `MeterApiUsage` — the cost lookup would just need to consider the caller's plan alongside the route name, which the plan is already resolved on `$request->user()` for.

Auto-recharge amounts ($10–$500) and the low-balance warning threshold ($1–$100, defaulting to $5) are both sound as configured — they're wide enough to fit a light user topping up $10 at a time and a heavy user pre-loading $500, without needing per-user negotiation.

## API feature roadmap

One thing is already ahead of where most small SaaS APIs are: outbound webhooks (`api_wallet.low_balance`, `api_wallet.topped_up`, plus generation/CRM/referral/earning events) are HMAC-SHA256 signed, retried on failure with exponential backoff, and logged per delivery. That's genuinely table-stakes-and-a-bit-more territory — most comparably-sized platforms don't have it at all.

Four gaps are worth closing first, because they benefit individuals and teams roughly equally and are each a contained piece of work:

An **idempotency key** on `POST /offers` and `POST /crm-contacts` would let a client safely retry a request that timed out without accidentally creating a duplicate offer or contact — a one-line header (`Idempotency-Key`) checked against a short-lived record before the request is processed. This has become close to a baseline expectation for any API that creates records.

**Scoped tokens** — a read-only option alongside today's full-access token — would matter most for teams: right now, handing a token to a contractor, an agency partner, or a read-only reporting tool means handing them the ability to spend the account's API wallet and create records too, whether or not that's wanted. A read-only token that can list offers, generations, and CRM contacts but never write or spend closes that gap.

**Plan-aware rate limits** would fix a real inconsistency: every token is capped at 60 requests/minute today regardless of plan, so a Business or Agency subscriber paying for a shared-team plan gets no more API headroom than someone on Starter. Something like 60/min for Starter and Growth, 120/min for Pro, and 300/min for Business and Agency would make the API scale the same way every other part of the plan already does.

A published **OpenAPI specification** would replace the static HTML endpoint table on the API Access page with something a developer can import directly into Postman or Insomnia, or use to generate a client library — and it's also the prerequisite for the SDK and Zapier work below, so it's worth sequencing early even though it has no visible feature of its own.

Three more are worth planning for once those land, roughly in order of effort:

A **sandbox mode** — a way to call the API against fake data without spending real wallet balance or dashboard credits — would let a developer build and test an integration risk-free before connecting it to a live account, which matters most for anyone building something more involved than a single Zap.

**Bulk endpoints** (starting with a `POST /crm-contacts/bulk`) would matter most for teams migrating in from another CRM or running a one-time import, where creating contacts one at a time is the real friction point today.

**Per-token usage visibility** on the API Access page — call volume and wallet spend broken down by token over time — would help any user with more than one integration understand which one is actually driving cost, using data the wallet ledger already records.

Further out, once the OpenAPI spec exists: official JS and Python SDKs (generated from the spec rather than hand-maintained), a native Zapier/Make integration (a natural fit given AffilStack's own audience already lives in marketing-automation tooling), and a "resend" action on webhook deliveries in the dashboard (the delivery log already exists — this is a small UI addition on top of it).

None of the roadmap items above have been built yet — this section is a recommendation to prioritize from, not a changelog. The wallet, auto-recharge, and the two new webhook events are what shipped this round.
