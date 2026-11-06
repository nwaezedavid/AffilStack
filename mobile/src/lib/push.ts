import AsyncStorage from '@react-native-async-storage/async-storage';
import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { Platform } from 'react-native';

import { api } from '@/lib/api';
import { DEMO_MODE } from '@/lib/config';
import { appPathForUrl } from '@/lib/links';

export type PushPermission = 'granted' | 'denied' | 'undetermined' | 'unsupported';

const ASKED_KEY = 'affilstack.push-asked';

const supported = Platform.OS !== 'web' && Device.isDevice;

if (Platform.OS !== 'web') {
  // Show notifications that arrive while the app is open, too.
  Notifications.setNotificationHandler({
    handleNotification: async () => ({ shouldPlaySound: true, shouldSetBadge: false, shouldShowBanner: true, shouldShowList: true }),
  });
}

export async function getPushPermission(): Promise<PushPermission> {
  if (DEMO_MODE) return 'granted';
  if (!supported) return 'unsupported';

  const { status } = await Notifications.getPermissionsAsync();

  return status === 'granted' ? 'granted' : status === 'denied' ? 'denied' : 'undetermined';
}

/**
 * Makes sure this phone can receive AffilStack notifications and tells the
 * server its push token. With ask, the phone's "Allow notifications?"
 * question is shown if it hasn't been answered yet; otherwise this only
 * refreshes the token when permission was already given.
 */
export async function registerForPush({ ask }: { ask: boolean }): Promise<PushPermission> {
  let status = await getPushPermission();

  if (DEMO_MODE || status === 'unsupported') return status;

  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('default', {
      name: 'Notifications',
      importance: Notifications.AndroidImportance.HIGH,
      lightColor: '#e2b24a',
    });
  }

  if (status === 'undetermined' && ask) {
    await AsyncStorage.setItem(ASKED_KEY, '1');
    const answer = await Notifications.requestPermissionsAsync();
    status = answer.status === 'granted' ? 'granted' : 'denied';
  }

  if (status !== 'granted') return status;

  const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;

  // Store builds always have one (set by `eas init`); a local preview may not.
  if (!projectId) return status;

  const { data: token } = await Notifications.getExpoPushTokenAsync({ projectId });

  await api('/v1/mobile/devices/current', { method: 'PUT', body: { push_provider: 'expo', push_token: token } });

  return status;
}

/** Asks for permission once, right after the first sign-in. */
export async function registerAfterSignIn(): Promise<void> {
  const asked = (await AsyncStorage.getItem(ASKED_KEY)) === '1';

  await registerForPush({ ask: !asked });
}

/**
 * Opens what a tapped notification (or link) points to: its app screen when
 * there is one, otherwise the page in the in-app browser.
 */
export function openLink(url: string | null | undefined): void {
  if (!url) return;

  const path = appPathForUrl(url);

  if (path) {
    router.push(path as never);
  } else if (/^https?:\/\//.test(url)) {
    void WebBrowser.openBrowserAsync(url);
  }
}

export function linkFromResponse(response: Notifications.NotificationResponse | null): string | null {
  const url = response?.notification.request.content.data?.url;

  return typeof url === 'string' ? url : null;
}
