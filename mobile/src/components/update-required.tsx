import * as Linking from 'expo-linking';
import { StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BrandMark } from '@/components/brand-mark';
import { Button } from '@/components/button';
import { Text } from '@/components/text';
import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

/**
 * Shown when this version is older than the minimum set in admin
 * (Website → Mobile Apps): the app can't continue until it's updated.
 */
export function UpdateRequired({ storeUrl, iconUrl }: { storeUrl: string | null; iconUrl?: string | null }) {
  const theme = useTheme();

  return (
    <SafeAreaView style={[styles.safe, { backgroundColor: theme.background }]}>
      <View style={styles.body}>
        <BrandMark size={72} iconUrl={iconUrl} />
        <Text variant="title" style={styles.center} accessibilityRole="header">
          Time for an update
        </Text>
        <Text tone="muted" style={styles.center}>
          This version of AffilStack is no longer supported. Update to the latest version to keep using the app.
        </Text>
        {storeUrl ? <Button label="Update now" icon="download-outline" variant="gold" onPress={() => Linking.openURL(storeUrl)} /> : null}
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1 },
  body: { flex: 1, justifyContent: 'center', padding: Spacing.xl, gap: Spacing.lg, maxWidth: 480, width: '100%', alignSelf: 'center', alignItems: 'stretch' },
  center: { textAlign: 'center' },
});
