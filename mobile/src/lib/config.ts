import Constants from 'expo-constants';

/**
 * Where the app talks to. Production is app.affilstack.com; a development
 * or preview build can point elsewhere with EXPO_PUBLIC_API_URL. Nothing
 * here is secret: the app is a public OAuth client protected by PKCE.
 */
export const API_URL = (process.env.EXPO_PUBLIC_API_URL ?? 'https://app.affilstack.com').replace(/\/+$/, '');

export const OAUTH_CLIENT_ID = 'affilstack-mobile';

/** Must match config('mobile.redirect_uris') on the server. */
export const OAUTH_REDIRECT_URI = 'affilstack://auth/callback';

/**
 * Demo mode serves built-in sample data instead of calling the server. Only
 * for design previews and screenshots — never enabled in store builds.
 */
export const DEMO_MODE = process.env.EXPO_PUBLIC_DEMO_MODE === '1';

export const APP_VERSION: string = Constants.expoConfig?.version ?? '1.0.0';
