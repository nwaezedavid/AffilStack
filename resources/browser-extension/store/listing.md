# Chrome Web Store listing — AffilStack v1.3.0

Copy these into the Chrome Developer Dashboard (Store listing + Privacy
practices tabs) when you upload the v1.3.0 zip. This folder (`store/`) is left
out of the zip customers download from the dashboard.

**v1.3.0 connects to app.affilstack.com, where the AffilStack dashboard now
lives (v1.2.0 pointed at affilstack.com). Nothing else changes: same name,
permissions and features. Google reviews every upload, usually within 1–3
business days.**

## Store listing

- **Name** (max 45): AffilStack — Leads & LinkedIn Assistant
- **Summary** (max 132): Add Google Maps businesses to your AffilStack CRM, write personalised LinkedIn messages and get AI replies that close.
- **Category**: Productivity → Workflow & Planning (or "Tools")
- **Language**: English

### Description

AffilStack's companion extension turns your prospecting research into
customers.

ADD GOOGLE MAPS BUSINESSES TO YOUR CRM
Search one of your product's Google Maps keywords, open a business — or keep
the results list open — and click the AffilStack icon. Choose the product
you're promoting and click "Add to CRM". The business name, category, address,
phone, website, rating and Maps link are saved to that product's contact list
in AffilStack, ready for an email campaign. Add a whole results page at once
with checkboxes. Businesses already in your CRM are marked so you never add
them twice.

WRITE THE PERFECT LINKEDIN MESSAGE IN ONE CLICK
On a LinkedIn profile or company page, pick your product and click "Generate
message". AffilStack's AI writes a personalised connection note (within
LinkedIn's character limit), a first message and two follow-ups based on the
details on that page. Copy them, or insert them into LinkedIn's message box —
then review and send them yourself.

AI REPLIES THAT MOVE PROSPECTS TO "YES"
Open a LinkedIn conversation and click "Suggest reply": the AI reads the
conversation and crafts the best response — it handles objections, proposes a
clear next step and adds your affiliate link only when the prospect is ready.
Got a reply by email or WhatsApp? Paste it into the Reply helper tab.

SAVE ANY PAGE TO YOUR RESEARCH
Save product pages, competitor ads and posts to AffilStack with one click.

PRIVATE BY DESIGN
The extension only reads the tab you're on, only when you click its icon, and
only sends data to your own AffilStack account when you click Add, Generate,
Suggest or Save. It never sends messages on your behalf.

Requires an AffilStack account (sign up at affilstack.com).

## Privacy practices tab

### Single purpose

Help AffilStack users turn the Google Maps and LinkedIn pages they are viewing
into CRM contacts and AI-written outreach messages in their own AffilStack
account.

### Permission justifications

- **activeTab** — When the user clicks the AffilStack toolbar icon, the
  extension needs temporary access to that one tab to read the business details
  (Google Maps) or profile/company/conversation details (LinkedIn) shown on it,
  and to insert a message the user chose into LinkedIn's message box. No access
  to any tab the user hasn't clicked the icon on.
- **scripting** — Used together with activeTab to inject the page reader
  (extractor.js, bundled in the extension) into the current tab at the moment
  the user opens the popup, and to insert text the user chose into the page.
  No content script runs automatically on any site.
- **storage** — Stores the user's AffilStack API token, the API URL and the
  last product they selected, locally in Chrome.
- **Host permissions** — None requested.
- **Remote code** — No. All JavaScript is bundled in the package; the
  extension only exchanges JSON with the AffilStack API.

### Data usage (check these boxes)

- **Personally identifiable information** — YES: names, job titles, locations
  and business contact details (phone, website) shown on the LinkedIn profile
  or Google Maps listing the user is viewing, sent to the user's own AffilStack
  account only when the user clicks Add to CRM / Generate message / Suggest
  reply.
- **Website content** — YES: text of the LinkedIn profile, company page or
  conversation the user is viewing, and the title/URL/selected text of a page
  the user saves, sent only when the user clicks a button.
- **Authentication information** — YES: the AffilStack API token the user
  pastes, stored locally and sent only to AffilStack's API.
- Health, financial/payment, personal communications (other than the
  conversation the user explicitly asks to reply to), location (GPS), web
  history, user activity — NO.

Certify all three: not sold to third parties; not used or transferred for
purposes unrelated to the single purpose; not used to determine
creditworthiness or for lending.

### Privacy policy URL

https://affilstack.com/privacy (make sure it mentions the browser extension:
what it reads, that it reads only on click, and that the data goes to the
user's own account).

## Screenshots to upload (1280×800 or 640×400)

1. Google Maps place page with the popup open on "Add to CRM".
2. Google Maps results list with several businesses ticked.
3. LinkedIn profile with a generated connection note and first message.
4. LinkedIn conversation with a suggested reply.
5. The AffilStack CRM list the businesses landed in.
