import Ionicons from '@expo/vector-icons/Ionicons';
import { StyleSheet, View } from 'react-native';

import { Card } from '@/components/card';
import { Text } from '@/components/text';
import { Palette, Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import type { ProgressTile } from '@/lib/types';

const ICONS: Record<string, keyof typeof Ionicons.glyphMap> = {
  products: 'search-circle-outline',
  content: 'document-text-outline',
  prospects: 'people-outline',
  emails: 'paper-plane-outline',
  clicks: 'hand-left-outline',
  sales: 'cash-outline',
  hours: 'time-outline',
};

const TONES: Record<string, string> = {
  violet: '#7c3aed',
  brand: Palette.brand500,
  emerald: Palette.emerald600,
  gold: Palette.gold600,
  rose: Palette.rose600,
};

/** One tile of the "What AffilStack has done for you" counter. */
export function StatTile({ tile }: { tile: ProgressTile }) {
  const theme = useTheme();
  const color = theme.scheme === 'dark' && tile.tone === 'gold' ? Palette.gold300 : (TONES[tile.tone] ?? theme.primary);

  return (
    <Card style={styles.tile} accessible accessibilityLabel={`${tile.label}: ${tile.value}${tile.week ? `, ${tile.week} this week` : ''}`}>
      <View style={[styles.icon, { backgroundColor: `${color}1a` }]}>
        <Ionicons name={ICONS[tile.key] ?? 'ellipse-outline'} size={18} color={color} />
      </View>
      <Text variant="heading">{tile.value}</Text>
      <Text variant="caption" tone="muted" numberOfLines={2}>
        {tile.label}
      </Text>
      {tile.note ? (
        <Text variant="label" tone="success">
          {tile.note}
        </Text>
      ) : tile.week > 0 ? (
        <Text variant="label" tone="success">
          +{tile.week} this week
        </Text>
      ) : null}
    </Card>
  );
}

const styles = StyleSheet.create({
  tile: { flexBasis: '47%', flexGrow: 1, gap: Spacing.xs, padding: Spacing.md },
  icon: { width: 32, height: 32, borderRadius: 10, alignItems: 'center', justifyContent: 'center', marginBottom: Spacing.xs },
});
