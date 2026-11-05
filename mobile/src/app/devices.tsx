import Ionicons from '@expo/vector-icons/Ionicons';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Screen } from '@/components/screen';
import { Text } from '@/components/text';
import { Spacing } from '@/constants/theme';
import { useDevices } from '@/hooks/use-api';
import { useTheme } from '@/hooks/use-theme';
import { api } from '@/lib/api';
import { confirm } from '@/lib/confirm';
import { relativeTime } from '@/lib/format';
import type { MobileDevice } from '@/lib/types';

/** Every phone signed in to the account; sign any other one out. */
export default function DevicesScreen() {
  const theme = useTheme();
  const devices = useDevices();
  const queryClient = useQueryClient();
  const signOutDevice = useMutation({
    mutationFn: (device: MobileDevice) => api(`/v1/mobile/devices/${device.id}`, { method: 'DELETE' }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['mobile', 'devices'] }),
  });

  const ask = async (device: MobileDevice) => {
    if (await confirm('Sign this phone out?', `${device.name ?? 'This phone'} will need to sign in again.`, 'Sign out', true)) {
      signOutDevice.mutate(device);
    }
  };

  return (
    <Screen edges={[]} onRefresh={() => devices.refetch()} refreshing={devices.isRefetching}>
      <Text tone="muted">Phones signed in to your AffilStack account. Don't recognise one? Sign it out and change your password on the website.</Text>
      {devices.isPending ? <ActivityIndicator color={theme.primary} /> : null}
      {devices.data?.map((device) => (
        <Card key={device.id} style={styles.row}>
          <Ionicons name={device.platform === 'ios' ? 'logo-apple' : device.platform === 'android' ? 'logo-android' : 'phone-portrait-outline'} size={26} color={theme.heading} />
          <View style={{ flex: 1 }}>
            <Text variant="bodyStrong" tone="heading">
              {device.name ?? 'Phone'}
              {device.is_current ? ' (this phone)' : ''}
            </Text>
            <Text variant="caption" tone="muted">
              App {device.app_version ?? '—'} · active {relativeTime(device.last_seen_at)}
            </Text>
          </View>
          {!device.is_current ? <Button label="Sign out" variant="secondary" onPress={() => ask(device)} loading={signOutDevice.isPending && signOutDevice.variables?.id === device.id} /> : null}
        </Card>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
});
