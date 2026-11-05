import Ionicons from '@expo/vector-icons/Ionicons';
import * as WebBrowser from 'expo-web-browser';
import { Pressable, StyleSheet, View } from 'react-native';

import { Text } from '@/components/text';
import { Radius, Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import type { MobileConfig } from '@/lib/types';

/** The banner set in admin (Website → Mobile Apps). */
export function AnnouncementBanner({ announcement }: { announcement: MobileConfig['announcement'] }) {
  const theme = useTheme();

  if (!announcement) return null;

  const tone = {
    info: { background: theme.infoSoft, text: theme.infoText, icon: 'information-circle' as const },
    warning: { background: theme.warningSoft, text: theme.warningText, icon: 'warning' as const },
    critical: { background: theme.dangerSoft, text: theme.danger, icon: 'alert-circle' as const },
  }[announcement.level];

  const body = (
    <View style={[styles.banner, { backgroundColor: tone.background }]}>
      <Ionicons name={tone.icon} size={18} color={tone.text} />
      <Text variant="caption" style={{ color: tone.text, flex: 1 }}>
        {announcement.message}
      </Text>
      {announcement.url ? <Ionicons name="chevron-forward" size={16} color={tone.text} /> : null}
    </View>
  );

  return announcement.url ? (
    <Pressable accessibilityRole="link" onPress={() => WebBrowser.openBrowserAsync(announcement.url as string)}>
      {body}
    </Pressable>
  ) : (
    <View accessibilityRole="alert">{body}</View>
  );
}

const styles = StyleSheet.create({
  banner: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm, padding: Spacing.md, borderRadius: Radius.md },
});
