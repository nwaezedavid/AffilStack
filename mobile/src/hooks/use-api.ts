import { useQuery } from '@tanstack/react-query';

import { api } from '@/lib/api';
import type { MobileConfig, MobileDevice, MobileHome } from '@/lib/types';

/** Start-up settings (forced update, banner). Works before sign-in. */
export function useAppConfig() {
  return useQuery({
    queryKey: ['mobile', 'config'],
    queryFn: () => api<MobileConfig>('/v1/mobile/config', { auth: false }),
    staleTime: 5 * 60_000,
  });
}

export function useHome(enabled = true) {
  return useQuery({
    queryKey: ['mobile', 'home'],
    queryFn: () => api<MobileHome>('/v1/mobile/home'),
    enabled,
  });
}

export function useDevices() {
  return useQuery({
    queryKey: ['mobile', 'devices'],
    queryFn: async () => (await api<{ data: MobileDevice[] }>('/v1/mobile/devices')).data,
  });
}
