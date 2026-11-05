import { API_URL, DEMO_MODE } from '@/lib/config';
import { demoResponse } from '@/lib/demo';
import { refreshTokens } from '@/lib/oauth';
import { clearTokens, loadTokens, saveTokens } from '@/lib/token-store';
import type { TokenSet } from '@/lib/types';

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    message: string,
    public readonly errors: Record<string, string[]> = {},
  ) {
    super(message);
  }
}

/** The server said the sign-in is no longer valid: the app signs out. */
export class SignedOutError extends ApiError {}

let refreshing: Promise<TokenSet | null> | null = null;

/**
 * One refresh at a time: if three requests find an expired token together,
 * they all wait for the same refresh (a second refresh with the same token
 * would be treated as a stolen token by the server and sign the phone out).
 */
async function refreshOnce(tokens: TokenSet): Promise<TokenSet | null> {
  refreshing ??= refreshTokens(tokens.refreshToken)
    .then(async (fresh) => {
      await saveTokens(fresh);

      return fresh;
    })
    .catch(async () => {
      await clearTokens();

      return null;
    })
    .finally(() => {
      refreshing = null;
    });

  return refreshing;
}

async function validTokens(): Promise<TokenSet | null> {
  const tokens = await loadTokens();

  if (!tokens) {
    return null;
  }

  return tokens.expiresAt > Date.now() ? tokens : refreshOnce(tokens);
}

type RequestOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
  /** For /mobile/config, which works before sign-in. */
  auth?: boolean;
};

/**
 * Calls the AffilStack API (paths are relative to /api, e.g.
 * "/v1/mobile/home"). Adds the bearer token, refreshes it when it has
 * expired, and retries once if the server rejects it mid-flight.
 */
export async function api<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = 'GET', body, auth = true } = options;

  if (DEMO_MODE) {
    return demoResponse<T>(method, path, body);
  }

  const send = async (tokens: TokenSet | null): Promise<Response> =>
    fetch(`${API_URL}/api${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
        ...(tokens ? { Authorization: `Bearer ${tokens.accessToken}` } : {}),
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });

  let tokens = auth ? await validTokens() : null;

  if (auth && !tokens) {
    throw new SignedOutError(401, 'Please sign in again.');
  }

  let response = await send(tokens);

  if (auth && response.status === 401 && tokens) {
    tokens = await refreshOnce(tokens);

    if (!tokens) {
      throw new SignedOutError(401, 'Please sign in again.');
    }

    response = await send(tokens);
  }

  const payload = (await response.json().catch(() => ({}))) as { message?: string; errors?: Record<string, string[]> };

  if (!response.ok) {
    if (auth && response.status === 401) {
      await clearTokens();

      throw new SignedOutError(401, payload.message ?? 'Please sign in again.');
    }

    throw new ApiError(response.status, payload.message ?? 'Something went wrong. Please try again.', payload.errors ?? {});
  }

  return payload as T;
}
