import { API_URL } from '@/lib/config';

const APP_HOST = new URL(API_URL).host;

/**
 * The AffilStack web pages the app has its own screen for, and which screen.
 * Used for links tapped on the phone (iPhone Universal Links, Android App
 * Links) and for tapped notifications, whose link is the same web address the
 * email would use. Must match config('mobile.app_links') on the server and the
 * Android intent filters in app.json.
 */
const ROUTES: { pattern: RegExp; to: (match: RegExpMatchArray) => string }[] = [
  { pattern: /^\/dashboard\/?$/, to: () => '/' },
  { pattern: /^\/offers\/?$/, to: () => '/products' },
  { pattern: /^\/offers\/(create|new)\/?$/, to: () => '/products/new' },
  { pattern: /^\/offers\/(\d+)(\/.*)?$/, to: (match) => `/products/${match[1]}` },
  { pattern: /^\/agents(\/.*)?$/, to: () => '/agents' },
  { pattern: /^\/crm\/?$/, to: () => '/contacts' },
  { pattern: /^\/crm\/inbox\/?$/, to: () => '/inbox' },
  { pattern: /^\/crm\/inbox\/(\d+)\/?$/, to: (match) => `/inbox/${match[1]}` },
];

/**
 * The app screen for an AffilStack web address, or null when the app has no
 * screen for it (or it isn't an AffilStack address) — open it in the browser
 * then.
 */
export function appPathForUrl(input: string | null | undefined): string | null {
  if (!input) return null;

  let url: URL;

  try {
    url = new URL(input);
  } catch {
    return null;
  }

  if ((url.protocol !== 'https:' && url.protocol !== 'http:') || url.host !== APP_HOST) return null;

  for (const route of ROUTES) {
    const match = url.pathname.match(route.pattern);

    if (match) return route.to(match);
  }

  return null;
}

/**
 * For +native-intent: what the router should open for a link the phone
 * handed to the app. AffilStack web links become app screens; the sign-in
 * return address (affilstack://auth/callback) is handled by the sign-in
 * screen itself, so the router just stays home.
 */
export function routeForSystemPath(path: string): string {
  try {
    const url = new URL(path, 'affilstack://app');

    if (url.protocol === 'affilstack:' && url.host === 'auth') return '/';
    if (url.protocol === 'https:' || url.protocol === 'http:') return appPathForUrl(path) ?? '/';

    return path;
  } catch {
    return '/';
  }
}
