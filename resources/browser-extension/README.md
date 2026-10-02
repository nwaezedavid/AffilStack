# AffilStack — Leads & LinkedIn Assistant (v1.2.0)

A Manifest V3 Chrome extension that connects your browsing to AffilStack:

- **Google Maps → CRM.** Search one of your product's Maps keywords, open a
  business (or keep the results list open) and click the AffilStack icon.
  Pick the product you're finding customers for and click **Add to CRM** —
  the business (name, category, address, phone, website, rating, Maps link)
  is filed in that product's list, ready for a campaign. Businesses already in
  your CRM are marked so you never add them twice.
- **LinkedIn messages.** On someone's profile or a company page, pick the
  product and click **Generate message** for a personalised connection note
  (within LinkedIn's 200-character limit for free accounts), a first message
  and two follow-ups. Copy them, or **Insert** straight into LinkedIn's open
  message / "Add a note" box.
- **AI reply assistant.** In a LinkedIn conversation, click **Suggest reply**
  for the best response to the prospect's latest message — it handles
  objections, proposes one next step and adds your affiliate link only when
  the prospect is ready. The **Reply helper** tab does the same for a reply
  you paste from email, WhatsApp or anywhere else.
- **Save page.** Save any page (a product page, a competitor ad, a post) to
  your AffilStack research, as in earlier versions.

AffilStack never sends anything on LinkedIn for you: you review every message
and press Send yourself.

## Install (unpacked)

1. Unzip this folder somewhere permanent — Chrome loads it from wherever it
   sits, and moving or deleting the folder later breaks the extension.
2. Open `chrome://extensions` in Chrome.
3. Turn on **Developer mode** (top right).
4. Click **Load unpacked** and select this `affilstack-extension` folder.
5. Pin the AffilStack icon to your toolbar for quick access.

Updating from v1.1: download the new zip, replace the old folder's contents,
then click the reload icon on the extension's card in `chrome://extensions`.
Your token is kept.

## Connect it to your account

1. In AffilStack, go to **Browser Extension** in the sidebar and generate a
   token (name it after the device, e.g. "Work laptop" — each device gets its
   own, so you can revoke one without affecting the others).
2. Click the AffilStack icon, then **Add your token** (or right-click the
   icon → **Options**).
3. Paste the token and click **Save & verify**. A green "Connected as …"
   confirms it. The API URL (under **Advanced**) is already filled in.

## Credits

Adding contacts is free (your plan's contact limit applies). Generating a
LinkedIn message pack and suggesting a reply use AI credits — the cost is
shown on each button.

## Permissions and privacy

The extension asks for `activeTab`, `scripting` and `storage` only — no
access to all websites, no background script and no content script that runs
by itself.

- It reads a page only when you click the AffilStack icon on it, and only
  that tab.
- On Google Maps it reads the business details shown on the page; on
  LinkedIn it reads the profile, company or conversation shown on the page.
- Nothing is sent to AffilStack until you click **Add to CRM**, **Generate**,
  **Suggest** or **Save** — and then only to your own AffilStack account.
- Your token and last-used product are stored locally in Chrome.
