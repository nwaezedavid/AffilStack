import Ionicons from '@expo/vector-icons/Ionicons';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { AnnouncementBanner } from '@/components/announcement-banner';
import { BrandMark } from '@/components/brand-mark';
import { Button } from '@/components/button';
import { Text } from '@/components/text';
import { Palette, Spacing } from '@/constants/theme';
import { useAppConfig } from '@/hooks/use-api';
import { useAuth } from '@/providers/auth-provider';

const POINTS: { icon: keyof typeof Ionicons.glyphMap; text: string }[] = [
  { icon: 'sparkles-outline', text: 'AI content and one-click videos for your products' },
  { icon: 'people-outline', text: 'Approve replies and leads your AI agents find' },
  { icon: 'stats-chart-outline', text: 'Clicks, sales, credits and wallet at a glance' },
];

/**
 * Welcome + sign-in. "Sign in" opens app.affilstack.com in the phone's
 * secure browser, where email + password (with two-factor) and Google work
 * exactly as on the website (Sign in with Apple joins them there later).
 */
export default function SignInScreen() {
  const { signIn } = useAuth();
  const config = useAppConfig();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const start = async () => {
    setBusy(true);
    setError(null);
    const result = await signIn();
    setBusy(false);

    if (!result.ok && result.message) setError(result.message);
  };

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.body}>
        <AnnouncementBanner announcement={config.data?.announcement ?? null} />
        <View style={styles.hero}>
          <BrandMark size={76} iconUrl={config.data?.brand.icon_url} name={config.data?.brand.name} onDark />
          <Text variant="title" style={styles.heroTitle} accessibilityRole="header">
            Your affiliate business, in your pocket
          </Text>
          <Text style={styles.heroText}>Everything you run on AffilStack — now on your phone.</Text>
        </View>

        <View style={styles.points}>
          {POINTS.map((point) => (
            <View key={point.text} style={styles.point}>
              <Ionicons name={point.icon} size={20} color={Palette.gold400} />
              <Text style={styles.pointText}>{point.text}</Text>
            </View>
          ))}
        </View>

        <View style={styles.actions}>
          {error ? (
            <Text variant="caption" style={styles.error} accessibilityRole="alert">
              {error}
            </Text>
          ) : null}
          <Button label="Sign in to AffilStack" icon="log-in-outline" variant="gold" loading={busy} onPress={start} accessibilityHint="Opens the secure AffilStack sign-in page" />
          <Text variant="caption" style={styles.small}>
            Email and password or Google — the same account you use on the website.
          </Text>
          {config.data?.links.forgot_password ? (
            <Pressable accessibilityRole="link" onPress={() => WebBrowser.openBrowserAsync(config.data.links.forgot_password)} style={styles.forgot}>
              <Text variant="bodyStrong" style={{ color: Palette.gold300 }}>
                Forgot password?
              </Text>
            </Pressable>
          ) : null}
        </View>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Palette.navy900 },
  body: { flex: 1, padding: Spacing.xl, gap: Spacing.xl, justifyContent: 'space-between', maxWidth: 520, width: '100%', alignSelf: 'center' },
  hero: { gap: Spacing.md, marginTop: Spacing.xl },
  heroTitle: { color: '#ffffff', fontSize: 30, lineHeight: 36 },
  heroText: { color: '#c3c9da' },
  points: { gap: Spacing.md },
  point: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  pointText: { color: '#e6e9f2', flex: 1 },
  actions: { gap: Spacing.sm },
  small: { color: Palette.ink400, textAlign: 'center' },
  error: { color: '#fda4af', textAlign: 'center' },
  forgot: { alignSelf: 'center', padding: Spacing.md, minHeight: 44 },
});
