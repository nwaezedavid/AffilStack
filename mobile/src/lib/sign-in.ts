import * as AuthSession from 'expo-auth-session';
import * as WebBrowser from 'expo-web-browser';

import { API_URL, DEMO_MODE, OAUTH_CLIENT_ID, OAUTH_REDIRECT_URI } from '@/lib/config';
import { OAuthError, exchangeCode } from '@/lib/oauth';
import type { TokenSet } from '@/lib/types';

WebBrowser.maybeCompleteAuthSession();

export type SignInResult = { type: 'success'; tokens: TokenSet } | { type: 'cancelled' } | { type: 'error'; message: string };

/**
 * Opens app.affilstack.com/mobile/authorize in the phone's secure browser
 * (it shares the website's sign-in, so password + 2FA and Google all work)
 * and swaps the returned code for tokens. PKCE (S256) and the state check
 * are handled by AuthRequest.
 */
export async function signInWithBrowser(): Promise<SignInResult> {
  if (DEMO_MODE) {
    return {
      type: 'success',
      tokens: { accessToken: 'demo', refreshToken: 'demo', expiresAt: Date.now() + 3600_000, deviceId: 1 },
    };
  }

  const request = new AuthSession.AuthRequest({
    clientId: OAUTH_CLIENT_ID,
    redirectUri: OAUTH_REDIRECT_URI,
    responseType: AuthSession.ResponseType.Code,
    usePKCE: true,
    codeChallengeMethod: AuthSession.CodeChallengeMethod.S256,
    scopes: [],
  });

  const result = await request.promptAsync({ authorizationEndpoint: `${API_URL}/mobile/authorize` });

  if (result.type === 'cancel' || result.type === 'dismiss') {
    return { type: 'cancelled' };
  }

  if (result.type !== 'success') {
    const error = result.type === 'error' ? result.error : null;

    return {
      type: 'error',
      message: error?.code === 'access_denied' ? 'Sign-in was cancelled.' : (error?.description ?? 'Sign-in did not finish. Please try again.'),
    };
  }

  if (!request.codeVerifier || typeof result.params.code !== 'string') {
    return { type: 'error', message: 'Sign-in did not finish. Please try again.' };
  }

  try {
    return { type: 'success', tokens: await exchangeCode(result.params.code, request.codeVerifier) };
  } catch (error) {
    return { type: 'error', message: error instanceof OAuthError ? error.message : 'Could not reach AffilStack. Check your connection and try again.' };
  }
}
