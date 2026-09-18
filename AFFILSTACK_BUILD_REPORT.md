# AffilStack — Affiliate Program Build Report

This report covers everything built in response to the affiliate program request: the landing page and signup, the approval workflow, the affiliate dashboard and subdomain separation, menu and pricing editability, credit top-ups, the brand logo marquee, testimonials, the Learning Centre, and the SEO/conversion pass — plus what to build next.

---

## 1. Affiliate landing page + earnings calculator

**Route:** `/affiliate`

The page was fully rewritten with new copywriting built around the actual commission terms (20% recurring, 60-day cookie, $50 payout minimum) instead of generic affiliate-program boilerplate. It includes:

- A hero with two calls to action — one to the application form, one to the earnings calculator.
- A live **earnings calculator**: the visitor picks a plan their referrals would likely buy and drags a slider for referrals per month, and the page computes recurring income at month 1, month 6, and month 12 (compounding, since commission is recurring) plus an estimated first-year total. It's explicitly labeled "illustrative estimate only" so it never reads as a guarantee.
- A "Built for people with an audience" section speaking directly to bloggers, YouTubers, newsletter writers, and community owners.
- `FAQPage` structured data so the page is eligible for rich results in Google search.

## 2. Application form + review workflow

Same page, `#apply` section. The form collects name, email, phone, promotion channels, a free-text message, and — new — **website/social URL, audience size, and experience level**, so you have enough context to actually evaluate applicants rather than just a name and email.

Every submission becomes an `AffiliateApplication` record. It shows up in the admin under **Affiliate Applications**, where you can review the full context (including the new fields) and **Approve** or **Decline**. Approving:

- Creates the affiliate's user account automatically.
- Flags the account `is_affiliate_only = true` — it exists to earn commission, not to buy a plan.
- Gives them a referral code and login access immediately.

## 3. Affiliate dashboard — separate, but centrally monitored

Rather than building a second application, the **existing** dashboard shell was re-themed conditionally: an affiliate-only account sees a distinct emerald "Partner Portal" sidebar, a banner clarifying "this account earns commission, it doesn't have platform access," and a commission-rate box in place of the Credits box a paying customer sees. A paying customer never sees any of this — it's gated strictly on `is_affiliate_only`.

Their dashboard shows: their tracking link (with one-click copy), link clicks, pending/approved/paid-out commission, payout method setup (PayPal or bank), a payout request button, and a list of people they've referred.

**On the subdomain:** `.env.example` now documents exactly what to set — `AFFILIATE_SUBDOMAIN=affiliate.yourdomain.com` plus `SESSION_DOMAIN=.yourdomain.com` (note the leading dot) so the login session is shared across both hosts. No duplicate routes were needed; the app's own `route()` URL generation already works correctly across both domains once those two settings are in place. **This is a deployment-time setting** — you'll set the real subdomain when you configure DNS and the production `.env`.

Every affiliate account still rolls up into the same central admin — there's one `AffiliateApplication`/User table, one `Affiliate Applications` screen, and one `Referral Payouts`/`Referral Commissions` screen to monitor everyone from.

## 4. Menu: "Affiliate Program" link

Added to the main site header, always visible (not tucked behind admin config, since the point was that people actually see it and register).

## 5–6. Menu is admin-editable — confirmed, not new

This was already built in an earlier phase: **Site Pages → Menu Items** in the admin lets you add, edit, reorder, or remove header links, stored as JSON and rendered live. The one exception is the Affiliate Program link itself, which is hardcoded so it's never accidentally removable — everything else on the menu is fully yours to control.

## 7. Pricing plans are admin-editable — confirmed, not new

Also already built: the **Plans** resource in the admin lets you edit price, credit allowance, features, and featured/active status for every plan, with the pricing pages reading live from the database.

## 8. Credit top-up purchases

**Route:** `/credits/top-up` (linked from the dashboard: "Buy more credits →")

Three packages, priced on a volume-discount curve:

| Package | Credits | Price | Per credit |
|---|---|---|---|
| Quick Top-Up | 200 | $19.00 | $0.0950 |
| Power Pack (featured) | 600 | $49.00 | $0.0817 |
| Bulk Pack | 1,500 | $109.00 | $0.0727 |

The math: your own AI cost per credit is roughly $0.008 (from the original plan pricing model), and your cheapest bundled plan rate (Agency tier) is about $0.057/credit. These three packages sit comfortably above cost and never undercut a subscription plan's own bundled rate — so buying a top-up is always convenient, never cheaper than just upgrading a plan.

All 4 existing payment gateways (Stripe, Flutterwave, Paystack, PayPal) support one-time top-up checkout, reusing the same country-based gateway selection your subscription checkout already uses. Credits post to the account instantly on successful payment and never expire. Prices are editable anytime from **Credit Top-Ups** in the admin (department: Billing).

## 9. Brand logos marquee

The homepage now has a "Trusted by marketers promoting" strip directly under the hero, with an infinite, smoothly looping horizontal scroll (pure CSS, pauses on hover, respects reduced-motion preferences). Manage the list from **Brand Logos** in the admin (department: Content) — upload a logo image, optional link, set order, toggle active. The list is unlimited, and the section disappears automatically if you haven't added any logos yet.

## 10. Testimonials — appear at 3+

New **Testimonials** admin screen. Add an author name, role, optional photo, quote, and 1–5 star rating, and publish it. The homepage testimonials section stays hidden until you have **3 published testimonials**, then appears automatically — the admin list view even tells you "Showing on the homepage — 3 published" or "Hidden until N more are published" so it's always clear where you stand. The homepage's star-rating structured data (`AggregateRating`) is computed strictly from your real published ratings and only appears once the visible section does — it will never claim a rating that isn't backed by content on the page.

## 11. Learning Centre (public tutorials page)

**Route:** `/learn`, linked in both the header and footer.

Manage tutorials from **Tutorials** in the admin: give it a title, optional description and category, and paste a YouTube link — it's embedded automatically (click-to-play, so the page doesn't load YouTube's player for every video up front). Tutorials are grouped by category on the public page, with an automatic "General" bucket for anything uncategorized. Currently seeded with 3 example tutorials across "Getting started" and "Payments" categories — replace these with real walkthroughs of your platform.

## 12. SEO & conversion enhancements built

- Twitter Card meta tags (`summary_large_image` when you've set a social image, `summary` otherwise) alongside the existing Open Graph tags.
- A prominent "Get started" button in the header nav (not just a text link), visible on every page.
- Sitemap now includes the affiliate landing page and Learning Centre at appropriately high priority, since both are acquisition/conversion pages in their own right, plus the About page.
- Real, gated `AggregateRating` structured data (see #10).
- An internal link from the homepage's "How it works" section to the Learning Centre, spreading link equity and giving hesitant visitors a lower-commitment next step than "buy now."

---

## Screenshots

15 screenshots covering every new and updated surface are attached to this conversation: the redesigned homepage (with the brand marquee and testimonials live), the affiliate landing page and its calculator in action, the Learning Centre, the credit top-up page, a paying customer's normal dashboard, an approved affiliate's "Partner Portal" dashboard, and the admin screens for Credit Packages, Brand Logos, Testimonials, Tutorials, Affiliate Applications, and Plans.

---

## Suggestions to build next

**Content & SEO**
- A blog/content-marketing engine targeting the long-tail keywords affiliate marketers actually search ("how to promote X affiliate program," "best AI content tools for affiliates") — you already have the AI content-generation pipeline internally; pointing a version of it at your own SEO content is the highest-leverage next step.
- Dedicated case-study pages per successful affiliate or customer, each with real numbers — much stronger conversion proof than a testimonial snippet, and each is its own indexable, shareable page.
- Core Web Vitals monitoring (Google Search Console is free and already has a verification meta tag wired up) so you catch performance regressions before they hurt rankings.

**Conversion**
- Exit-intent capture on the pricing and affiliate landing pages (email capture with a lead magnet — e.g., "the exact outreach script our top affiliate uses") for visitors who aren't ready to sign up yet.
- A/B testing on the pricing page layout and the calculator's default slider position — small framing changes there tend to move conversion more than most other changes.
- Video testimonials to sit alongside the text ones once you have a few — much higher trust signal, and you already have the YouTube-embed pattern built for tutorials, so reusing it here is close to free.
- Retargeting pixels (Meta/Google) on the homepage and affiliate landing page for visitors who don't convert on the first visit.

**Platform efficiency**
- A lightweight leaderboard or recognition system for top affiliates (even just "Affiliate of the Month" on their dashboard) — a cheap way to increase referral activity without changing the commission structure.
- Real analytics on the affiliate landing page's calculator interactions (which plan/referral combinations people actually explore) to see what conversion assumptions are realistic versus aspirational, and tune the copy accordingly.
- Automated low-testimonial-count alerts to the admin (e.g., "You're at 2 of 3 — one more testimonial and the homepage section goes live") so the gating logic in #10 doesn't quietly sit unused.
