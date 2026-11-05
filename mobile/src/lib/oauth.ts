import { Platform } from 'react-native';
import * as Device from 'expo-device';

import { API_URL, APP_VERSION, OAUTH_CLIENT_ID, OAUTH_REDIRECT_URI } from '@/lib/config';
import type { TokenSet } from '@/lib/types';

type TokenResponse = {
  access_token: string;
  refresh_token: string;
  expires_in: number;
  device_id?: number;
};

export class OAuthError extends Error {
  constructor(
    public readonly code: string,
    message: string,
  ) {
    super(message);
  }
}

function toTokenSet(body: TokenResponse): TokenSet {
  return {
    accessToken: body.access_token,
    refreshToken: body.refresh_token,
    // Refresh a minute early so a request never races the expiry.
    expiresAt: Date.now() + Math.max(body.expires_in - 60, 30) * 1000,
    deviceId: body.device_id ?? null,
  };
}

async function postToken(params: Record<string, string>): Promise<TokenSet> {
  const response = await fetch(`${API_URL}/api/oauth/token`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ client_id: OAUTH_CLIENT_ID, ...params }),
  });

  const body = (await response.json().catch(() => ({}))) as Partial<TokenResponse> & {
    error?: string;
    error_description?: string;
  };

  if (!response.ok || !body.access_token || !body.refresh_token) {
    throw new OAuthError(body.error ?? 'server_error', body.error_description ?? 'Sign-in failed. Please try again.');
  }

  return toTokenSet(body as TokenResponse);
}

export function currentPlatform(): 'ios' | 'android' | 'web' | 'unknown' {
  return Platform.OS === 'ios' || Platform.OS === 'android' || Platform.OS === 'web' ? Platform.OS : 'unknown';
}

/**
 * Swaps the one-time code from app.affilstack.com/mobile/authorize (plus
 * the PKCE verifier only this app knows) for tokens.
 */
export function exchangeCode(code: string, codeVerifier: string): Promise<TokenSet> {
  return postToken({
    grant_type: 'authorization_code',
    code,
    code_verifier: codeVerifier,
    redirect_uri: OAUTH_REDIRECT_URI,
    device_name: Device.deviceName ?? Device.modelName ?? 'Phone',
    platform: currentPlatform(),
    app_version: APP_VERSION,
  });
}

/** Refresh tokens rotate: the old one stops working once this succeeds. */
export function refreshTokens(refreshToken: string): Promise<TokenSet> {
  return postToken({ grant_type: 'refresh_token', refresh_token: refreshToken });
}

export async function revokeToken(token: string): Promise<void> {
  await fetch(`${API_URL}/api/oauth/revoke`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ client_id: OAUTH_CLIENT_ID, token }),
  }).catch(() => undefined);
}
