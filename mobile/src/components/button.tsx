import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';

import { Text } from '@/components/text';
import { Palette, Radius, Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

type Props = {
  label: string;
  onPress: () => void;
  variant?: 'primary' | 'gold' | 'secondary' | 'danger' | 'ghost';
  icon?: keyof typeof Ionicons.glyphMap;
  loading?: boolean;
  disabled?: boolean;
  accessibilityHint?: string;
};

export function Button({ label, onPress, variant = 'primary', icon, loading, disabled, accessibilityHint }: Props) {
  const theme = useTheme();
  const palette = {
    primary: { background: theme.primary, text: theme.primaryText, border: theme.primary },
    gold: { background: Palette.gold400, text: Palette.navy900, border: Palette.gold400 },
    secondary: { background: theme.card, text: theme.heading, border: theme.border },
    danger: { background: theme.dangerSoft, text: theme.danger, border: theme.dangerSoft },
    ghost: { background: 'transparent', text: theme.primary, border: 'transparent' },
  }[variant];
  const inactive = disabled || loading;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityHint={accessibilityHint}
      accessibilityState={{ disabled: inactive, busy: loading }}
      disabled={inactive}
      onPress={onPress}
      style={({ pressed }) => [
        styles.button,
        { backgroundColor: palette.background, borderColor: palette.border, opacity: inactive ? 0.6 : pressed ? 0.85 : 1 },
      ]}>
      <View style={styles.row}>
        {loading ? <ActivityIndicator color={palette.text} /> : icon ? <Ionicons name={icon} size={18} color={palette.text} /> : null}
        <Text variant="bodyStrong" style={{ color: palette.text }}>
          {label}
        </Text>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  button: {
    minHeight: 50,
    borderRadius: Radius.md,
    borderWidth: 1,
    paddingHorizontal: Spacing.lg,
    justifyContent: 'center',
  },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: Spacing.sm },
});
