import { StyleSheet, View, type ViewProps } from 'react-native';

import { Radius, Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

export function Card({ style, muted, ...props }: ViewProps & { muted?: boolean }) {
  const theme = useTheme();

  return <View {...props} style={[styles.card, { backgroundColor: muted ? theme.cardMuted : theme.card, borderColor: theme.border }, style]} />;
}

const styles = StyleSheet.create({
  card: { borderRadius: Radius.lg, borderWidth: StyleSheet.hairlineWidth, padding: Spacing.lg },
});
