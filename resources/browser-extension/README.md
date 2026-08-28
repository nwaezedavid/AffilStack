# AffiliStack Research Capture

A small Manifest V3 Chrome extension that saves the page you're on — a
product page, a competitor ad, a LinkedIn post — into your AffiliStack
account, so you're not copy-pasting URLs and screenshots by hand.

## Install (unpacked)

1. Unzip this folder somewhere permanent — Chrome loads it from wherever it
   sits, and moving or deleting the folder later breaks the extension.
2. Open `chrome://extensions` in Chrome.
3. Turn on **Developer mode** (top right).
4. Click **Load unpacked** and select this `affilistack-extension` folder.
5. Pin the AffiliStack icon to your toolbar if you'd like quick access.

## Connect it to your account

1. In AffiliStack, go to **Browser Extension** in the sidebar and generate a
   token (give it a name like "Work laptop" — each device should get its
   own, so you can revoke one without affecting the others).
2. Click the AffiliStack icon in Chrome, then open its options (or
   right-click the icon → **Options**).
3. Paste in your AffiliStack API URL and the token, then **Save & verify**.

## Use it

Open the popup on any page you want to save. It picks up the page title and
URL automatically, and includes any text you'd selected on the page before
opening the popup. Choose a page type, optionally attach it to one of your
offers, and save. From the **Browser Extension** page in AffiliStack you can
attach a capture to an offer later, or turn an unattached one straight into
a new offer.

## Permissions

The extension asks for `activeTab`, `scripting`, and `storage` only — it
never runs on a page unless you open the popup on it, and it doesn't run in
the background or read pages you haven't opened the popup on.
