import type { TokenSet } from '@/lib/types';
import { deleteSecureItem, getSecureItem, setSecureItem } from '@/lib/secure-storage';

const KEY = 'affilstack.tokens.v1';

let cached: TokenSet | null | undefined;
const listeners = new Set<(tokens: TokenSet | null) => void>();

/**
 * The signed-in device's tokens, kept in the Keychain/Keystore and mirrored
 * in memory so every API call doesn't hit secure storage.
 */
export async function loadTokens(): Promise<TokenSet | null> {
  if (cached !== undefined) {
    return cached;
  }

  const raw = await getSecureItem(KEY);

  try {
    cached = raw ? (JSON.parse(raw) as TokenSet) : null;
  } catch {
    cached = null;
  }

  return cached;
}

export async function saveTokens(tokens: TokenSet): Promise<void> {
  cached = tokens;
  await setSecureItem(KEY, JSON.stringify(tokens));
  listeners.forEach((listener) => listener(tokens));
}

export async function clearTokens(): Promise<void> {
  cached = null;
  await deleteSecureItem(KEY);
  listeners.forEach((listener) => listener(null));
}

/** Called whenever the app signs in or is signed out (e.g. a revoked token). */
export function onTokensChange(listener: (tokens: TokenSet | null) => void): () => void {
  listeners.add(listener);

  return () => listeners.delete(listener);
}

/** Test helper: forget the in-memory copy. */
export function resetTokenCacheForTests(): void {
  cached = undefined;
  listeners.clear();
}
