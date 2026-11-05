# AffilStack mobile app

The official AffilStack app for Android and iOS — Expo (React Native +
TypeScript), Expo Router, built and submitted with EAS. It talks to the
Laravel API at `/api/v1` (see `resources/openapi/openapi.yaml`, "Mobile app").

This folder is excluded from the Hostinger server package (`/mobile
export-ignore` in the root `.gitattributes`).

## Commands

```bash
npm ci                # install
npm start             # dev server (needs a development build on a phone)
npm run typecheck
npm test
npm run preview:web   # web preview with built-in sample data (dist/)
```

## How sign-in works

The app opens `https://app.affilstack.com/mobile/authorize` in the phone's
secure browser (OAuth 2.0 authorization code + PKCE, client id
`affilstack-mobile`, redirect `affilstack://auth/callback`), then swaps the
code at `/api/oauth/token`. Tokens are kept in the iOS Keychain / Android
Keystore (`expo-secure-store`). Access tokens last an hour; refresh tokens
rotate on every use.

## Configuration

- App IDs: `com.affilstack.app` (iOS bundle id and Android package).
- `EXPO_PUBLIC_API_URL` points a build at another server (defaults to
  `https://app.affilstack.com`).
- `EXPO_PUBLIC_DEMO_MODE=1` serves sample data — previews only, never in
  store builds.
- No secrets live in this folder. Push and store keys are entered in
  AffilStack admin or in EAS.
