import Ionicons from '@expo/vector-icons/Ionicons';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { ActivityIndicator, Alert, Linking, Platform, StyleSheet, Switch, View } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { ErrorCard } from '@/components/kit';
import { Screen } from '@/components/screen';
import { Text } from '@/components/text';
import { Spacing } from '@/constants/theme';
import { useNotificationPreferences, useRefreshNotificationPreferences, useSendTestNotification, useUpdateNotificationPreference } from '@/hooks/use-push';
import { useTheme } from '@/hooks/use-theme';
import { ApiError } from '@/lib/api';
import { getPushPermission, registerForPush, type PushPermission } from '@/lib/push';

/**
 * More > Notifications: whether this phone accepts AffilStack
 * notifications, which kinds the customer wants (for all their phones),
 * and a test notification.
 */
export default function NotificationsScreen() {
  const theme = useTheme();
  const preferences = useNotificationPreferences();
  const refresh = useRefreshNotificationPreferences();
  const update = useUpdateNotificationPreference();
  const test = useSendTestNotification();
  const [permission, setPermission] = useState<PushPermission | null>(null);
  const [turningOn, setTurningOn] = useState(false);

  // Coming back from the phone's settings: show the new answer.
  useFocusEffect(
    useCallback(() => {
      void getPushPermission().then(setPermission);
    }, []),
  );

  const turnOn = async () => {
    setTurningOn(true);

    try {
      const status = await registerForPush({ ask: true });
      setPermission(status);
      await refresh();
    } catch {
      Alert.alert('Couldn\'t turn on notifications', 'Please check your connection and try again.');
    } finally {
      setTurningOn(false);
    }
  };

  const sendTest = () =>
    test.mutate(undefined, {
      onSuccess: (response) => Alert.alert('Sent', response.message),
      onError: (error) => Alert.alert('Not sent', error instanceof ApiError ? error.message : 'Please try again in a minute.'),
    });

  const data = preferences.data;

  return (
    <Screen edges={[]} onRefresh={() => preferences.refetch()} refreshing={preferences.isRefetching}>
      <Card style={styles.status}>
        <Ionicons
          name={permission === 'granted' ? 'notifications' : 'notifications-off-outline'}
          size={28}
          color={permission === 'granted' ? theme.success : theme.textMuted}
        />
        <View style={{ flex: 1, gap: Spacing.xs }}>
          <Text variant="bodyStrong" tone="heading">
            {permission === 'granted'
              ? 'Notifications are on for this phone'
              : permission === 'denied'
                ? 'Notifications are off for AffilStack'
                : permission === 'unsupported'
                  ? 'Notifications need the phone app'
                  : 'Get told when things happen'}
          </Text>
          <Text variant="caption" tone="muted">
            {permission === 'granted'
              ? 'You\'ll hear about the kinds switched on below.'
              : permission === 'denied'
                ? 'Your phone is blocking them. Turn them on for AffilStack in your phone\'s settings.'
                : permission === 'unsupported'
                  ? 'Open AffilStack on your iPhone or Android phone to get notifications.'
                  : 'Know the moment your content is ready or a buyer replies.'}
          </Text>
          {permission === 'undetermined' ? <Button label="Turn on notifications" icon="notifications-outline" onPress={turnOn} loading={turningOn} /> : null}
          {permission === 'denied' && Platform.OS !== 'web' ? <Button label="Open phone settings" variant="secondary" icon="settings-outline" onPress={() => void Linking.openSettings()} /> : null}
        </View>
      </Card>

      {data && !data.push_available ? (
        <Card>
          <Text variant="caption" tone="muted">
            AffilStack hasn't started sending phone notifications yet. Your choices below are saved for when it does.
          </Text>
        </Card>
      ) : null}

      {preferences.isPending ? <ActivityIndicator color={theme.primary} /> : null}
      {preferences.isError ? <ErrorCard onRetry={() => preferences.refetch()} /> : null}

      {data ? (
        <Card>
          <Text variant="label" tone="faint" style={styles.section}>
            TELL ME ABOUT
          </Text>
          {data.categories.map((category, index) => (
            <View key={category.key} style={[styles.row, index > 0 && { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.border }]}>
              <View style={{ flex: 1 }}>
                <Text variant="bodyStrong" tone="heading">
                  {category.label}
                </Text>
                <Text variant="caption" tone="muted">
                  {category.description}
                </Text>
              </View>
              <Switch
                value={category.enabled}
                onValueChange={(enabled) => update.mutate({ key: category.key, enabled })}
                trackColor={{ true: theme.primary }}
                accessibilityLabel={category.label}
              />
            </View>
          ))}
          <Text variant="caption" tone="faint" style={{ marginTop: Spacing.sm }}>
            These apply to every phone you're signed in on. Emails are set on the website.
          </Text>
        </Card>
      ) : null}

      {data?.push_available && permission === 'granted' ? (
        <Button label="Send a test notification" variant="secondary" icon="paper-plane-outline" onPress={sendTest} loading={test.isPending} />
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  status: { flexDirection: 'row', alignItems: 'flex-start', gap: Spacing.md },
  section: { letterSpacing: 0.8, marginBottom: Spacing.xs },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md, paddingVertical: Spacing.sm },
});
