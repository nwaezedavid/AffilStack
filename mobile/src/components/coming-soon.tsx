import Ionicons from '@expo/vector-icons/Ionicons';
import { StyleSheet, View } from 'react-native';

import { Card } from '@/components/card';
import { Screen } from '@/components/screen';
import { Text } from '@/components/text';
import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

type Props = { title: string; icon: keyof typeof Ionicons.glyphMap; description: string; bullets: string[] };

/** A section that arrives in the next build (phase 4). */
export function ComingSoon({ title, icon, description, bullets }: Props) {
  const theme = useTheme();

  return (
    <Screen>
      <Text variant="title" accessibilityRole="header">
        {title}
      </Text>
      <Card style={styles.card}>
        <View style={[styles.icon, { backgroundColor: theme.infoSoft }]}>
          <Ionicons name={icon} size={26} color={theme.primary} />
        </View>
        <Text variant="subheading">{description}</Text>
        {bullets.map((bullet) => (
          <View key={bullet} style={styles.bullet}>
            <Ionicons name="checkmark-circle" size={18} color={theme.success} />
            <Text tone="muted" style={{ flex: 1 }}>
              {bullet}
            </Text>
          </View>
        ))}
        <Text variant="caption" tone="faint">
          Coming in the next update.
        </Text>
      </Card>
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: { gap: Spacing.md },
  icon: { width: 52, height: 52, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
  bullet: { flexDirection: 'row', gap: Spacing.sm, alignItems: 'flex-start' },
});
