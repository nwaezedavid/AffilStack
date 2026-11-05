import AsyncStorage from '@react-native-async-storage/async-storage';
import { useQueryClient } from '@tanstack/react-query';
import * as LocalAuthentication from 'expo-local-authentication';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { AppState, Platform } from 'react-native';

import { api } from '@/lib/api';
import { revokeToken } from '@/lib/oauth';
import { signInWithBrowser } from '@/lib/sign-in';
import { clearTokens, loadTokens, onTokensChange, saveTokens } from '@/lib/token-store';

const BIOMETRIC_KEY = 'affilstack.biometric-lock';

/** Background time after which the biometric lock asks again. */
const RELOCK_AFTER_MS = 5 * 60 * 1000;

export type AuthStatus = 'loading' | 'signed-out' | 'locked' | 'signed-in';

type AuthContextValue = {
  status: AuthStatus;
  signIn: () => Promise<{ ok: boolean; message?: string }>;
  signOut: () => Promise<void>;
  signOutEverywhere: () => Promise<void>;
  /** After the account was deleted: forget everything locally. */
  forgetDevice: () => Promise<void>;
  biometricAvailable: boolean;
  biometricEnabled: boolean;
  setBiometricEnabled: (enabled: boolean) => Promise<boolean>;
  unlock: () => Promise<boolean>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

async function authenticate(reason: string): Promise<boolean> {
  const result = await LocalAuthentication.authenticateAsync({ promptMessage: reason, cancelLabel: 'Cancel' });

  return result.success;
}

/**
 * Who is signed in on this phone, plus the optional Face ID / fingerprint
 * lock. Signing out (here, from another device, by a password change or a
 * revoked token) clears the tokens and the cached data.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState<AuthStatus>('loading');
  const [biometricAvailable, setBiometricAvailable] = useState(false);
  const [biometricEnabled, setBiometricEnabledState] = useState(false);
  const backgroundedAt = useRef<number | null>(null);

  useEffect(() => {
    let active = true;

    (async () => {
      const [tokens, stored, hasHardware, enrolled] = await Promise.all([
        loadTokens(),
        AsyncStorage.getItem(BIOMETRIC_KEY),
        Platform.OS === 'web' ? false : LocalAuthentication.hasHardwareAsync(),
        Platform.OS === 'web' ? false : LocalAuthentication.isEnrolledAsync(),
      ]);
      const canUseBiometrics = Boolean(hasHardware && enrolled);
      const lockOn = canUseBiometrics && stored === '1';

      if (!active) return;

      setBiometricAvailable(canUseBiometrics);
      setBiometricEnabledState(lockOn);
      setStatus(!tokens ? 'signed-out' : lockOn ? 'locked' : 'signed-in');
    })();

    const unsubscribe = onTokensChange((tokens) => {
      if (!tokens) {
        queryClient.clear();
        setStatus('signed-out');
      }
    });

    return () => {
      active = false;
      unsubscribe();
    };
  }, [queryClient]);

  useEffect(() => {
    const subscription = AppState.addEventListener('change', (next) => {
      if (next === 'background') {
        backgroundedAt.current = Date.now();
      } else if (next === 'active' && backgroundedAt.current !== null) {
        const away = Date.now() - backgroundedAt.current;
        backgroundedAt.current = null;

        if (biometricEnabled && away > RELOCK_AFTER_MS) {
          setStatus((current) => (current === 'signed-in' ? 'locked' : current));
        }
      }
    });

    return () => subscription.remove();
  }, [biometricEnabled]);

  const signIn = useCallback(async () => {
    const result = await signInWithBrowser();

    if (result.type === 'success') {
      await saveTokens(result.tokens);
      setStatus('signed-in');

      return { ok: true };
    }

    return { ok: false, message: result.type === 'error' ? result.message : undefined };
  }, []);

  const forgetDevice = useCallback(async () => {
    await clearTokens();
    queryClient.clear();
    setStatus('signed-out');
  }, [queryClient]);

  const signOut = useCallback(async () => {
    const tokens = await loadTokens();

    try {
      await api('/v1/mobile/logout', { method: 'POST' });
    } catch {
      // Offline or already signed out: still revoke the refresh token if we can.
      if (tokens) await revokeToken(tokens.refreshToken);
    }

    await forgetDevice();
  }, [forgetDevice]);

  const signOutEverywhere = useCallback(async () => {
    await api('/v1/mobile/logout-everywhere', { method: 'POST' });
    await forgetDevice();
  }, [forgetDevice]);

  const setBiometricEnabled = useCallback(async (enabled: boolean) => {
    if (enabled && !(await authenticate('Turn on Face ID / fingerprint unlock'))) {
      return false;
    }

    await AsyncStorage.setItem(BIOMETRIC_KEY, enabled ? '1' : '0');
    setBiometricEnabledState(enabled);

    return true;
  }, []);

  const unlock = useCallback(async () => {
    const ok = await authenticate('Unlock AffilStack');

    if (ok) setStatus('signed-in');

    return ok;
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({ status, signIn, signOut, signOutEverywhere, forgetDevice, biometricAvailable, biometricEnabled, setBiometricEnabled, unlock }),
    [status, signIn, signOut, signOutEverywhere, forgetDevice, biometricAvailable, biometricEnabled, setBiometricEnabled, unlock],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used inside <AuthProvider>.');
  }

  return context;
}
