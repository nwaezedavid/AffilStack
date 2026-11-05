import { useEffect } from 'react';
import { StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BrandMark } from '@/components/brand-mark';
import { Button } from '@/components/button';
import { Text } from '@/components/text';
import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { useAuth } from '@/providers/auth-provider';

/** Face ID / fingerprint unlock, when switched on in More → Security. */
export function LockScreen() {
  const theme = useTheme();
  const { unlock, signOut } = useAuth();

  useEffect(() => {
    unlock();
  }, [unlock]);

  return (
    <SafeAreaView style={[styles.safe, { backgroundColor: theme.background }]}>
      <View style={styles.body}>
        <BrandMark size={72} />
        <Text variant="title" style={styles.center} accessibilityRole="header">
          AffilStack is locked
        </Text>
        <Text tone="muted" style={styles.center}>
          Unlock with Face ID, Touch ID or your fingerprint to continue.
        </Text>
        <Button label="Unlock" icon="finger-print" onPress={unlock} />
        <Button label="Sign out instead" variant="ghost" onPress={signOut} />
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1 },
  body: { flex: 1, justifyContent: 'center', padding: Spacing.xl, gap: Spacing.lg, maxWidth: 480, width: '100%', alignSelf: 'center' },
  center: { textAlign: 'center' },
});
