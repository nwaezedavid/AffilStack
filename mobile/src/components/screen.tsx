import type { ReactNode } from 'react';
import { RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView, type Edge } from 'react-native-safe-area-context';

import { MaxContentWidth, Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

type Props = {
  children: ReactNode;
  /** Pull to refresh. */
  onRefresh?: () => void;
  refreshing?: boolean;
  edges?: Edge[];
  scroll?: boolean;
};

/** Page wrapper: safe areas, brand background, centred column on tablets. */
export function Screen({ children, onRefresh, refreshing = false, edges = ['top'], scroll = true }: Props) {
  const theme = useTheme();
  const content = <View style={styles.column}>{children}</View>;

  return (
    <SafeAreaView edges={edges} style={[styles.safe, { backgroundColor: theme.background }]}>
      {scroll ? (
        <ScrollView
          contentContainerStyle={styles.scroll}
          refreshControl={onRefresh ? <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={theme.primary} /> : undefined}>
          {content}
        </ScrollView>
      ) : (
        <View style={[styles.scroll, { flex: 1 }]}>{content}</View>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1 },
  scroll: { padding: Spacing.lg, paddingBottom: Spacing.xxl },
  column: { width: '100%', maxWidth: MaxContentWidth, alignSelf: 'center', gap: Spacing.lg },
});
