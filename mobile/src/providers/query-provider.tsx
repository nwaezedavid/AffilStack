import AsyncStorage from '@react-native-async-storage/async-storage';
import { createAsyncStoragePersister } from '@tanstack/query-async-storage-persister';
import { QueryClient, focusManager, onlineManager } from '@tanstack/react-query';
import { PersistQueryClientProvider } from '@tanstack/react-query-persist-client';
import { useEffect, useState, type ReactNode } from 'react';
import { AppState, Platform } from 'react-native';

import { ApiError } from '@/lib/api';

/**
 * Data fetching with an offline cache: the last Home, Products, etc. are
 * saved on the phone for a day, so the app opens instantly and still shows
 * something without signal. Refreshes when the app comes back to the front.
 */
export function QueryProvider({ children }: { children: ReactNode }) {
  const [client] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 60_000,
            gcTime: 24 * 60 * 60 * 1000,
            retry: (failures, error) => !(error instanceof ApiError && error.status < 500) && failures < 2,
          },
        },
      }),
  );
  const [persister] = useState(() => createAsyncStoragePersister({ storage: AsyncStorage, key: 'affilstack.cache.v1' }));

  useEffect(() => {
    if (Platform.OS === 'web') return;

    const subscription = AppState.addEventListener('change', (state) => focusManager.setFocused(state === 'active'));

    return () => subscription.remove();
  }, []);

  useEffect(() => {
    onlineManager.setOnline(true);
  }, []);

  return (
    <PersistQueryClientProvider client={client} persistOptions={{ persister, maxAge: 24 * 60 * 60 * 1000, buster: 'v1' }}>
      {children}
    </PersistQueryClientProvider>
  );
}
