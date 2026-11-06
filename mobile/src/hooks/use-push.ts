import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as Notifications from 'expo-notifications';
import { useEffect } from 'react';
import { Platform } from 'react-native';

import { api } from '@/lib/api';
import { linkFromResponse, openLink, registerAfterSignIn } from '@/lib/push';
import type { NotificationPreferences } from '@/lib/types';

/**
 * While signed in: registers this phone for notifications (asking once) and
 * opens whatever a tapped notification points to — including the one that
 * launched the app.
 */
export function usePushNotifications(enabled: boolean) {
  useEffect(() => {
    if (!enabled || Platform.OS === 'web') return;

    registerAfterSignIn().catch(() => undefined);

    const launched = Notifications.getLastNotificationResponse();

    if (launched) {
      Notifications.clearLastNotificationResponse();
      openLink(linkFromResponse(launched));
    }

    const subscription = Notifications.addNotificationResponseReceivedListener((response) => openLink(linkFromResponse(response)));

    return () => subscription.remove();
  }, [enabled]);
}

const KEY = ['notification-preferences'];

export function useNotificationPreferences() {
  return useQuery({ queryKey: KEY, queryFn: () => api<{ data: NotificationPreferences }>('/v1/mobile/notifications/preferences'), select: (response) => response.data });
}

export function useUpdateNotificationPreference() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ key, enabled }: { key: string; enabled: boolean }) =>
      api<{ data: NotificationPreferences }>('/v1/mobile/notifications/preferences', { method: 'PUT', body: { categories: { [key]: enabled } } }),
    onMutate: async ({ key, enabled }) => {
      await queryClient.cancelQueries({ queryKey: KEY });
      const previous = queryClient.getQueryData<{ data: NotificationPreferences }>(KEY);

      if (previous) {
        queryClient.setQueryData(KEY, {
          data: { ...previous.data, categories: previous.data.categories.map((category) => (category.key === key ? { ...category, enabled } : category)) },
        });
      }

      return { previous };
    },
    onError: (_error, _variables, context) => {
      if (context?.previous) queryClient.setQueryData(KEY, context.previous);
    },
    onSuccess: (response) => queryClient.setQueryData(KEY, response),
  });
}

export function useSendTestNotification() {
  return useMutation({ mutationFn: () => api<{ message: string }>('/v1/mobile/notifications/test', { method: 'POST' }) });
}

export function useRefreshNotificationPreferences() {
  const queryClient = useQueryClient();

  return () => queryClient.invalidateQueries({ queryKey: KEY });
}
