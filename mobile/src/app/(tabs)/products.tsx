import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { useDeferredValue, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { EmptyState, ErrorCard, SearchBox, StatusBadge } from '@/components/kit';
import { Text } from '@/components/text';
import { MaxContentWidth, Spacing } from '@/constants/theme';
import { useOffers } from '@/hooks/use-core';
import { useTheme } from '@/hooks/use-theme';
import { relativeTime } from '@/lib/format';
import type { Offer } from '@/lib/types';

/** Products: the customer's researched products, searchable. */
export default function ProductsScreen() {
  const theme = useTheme();
  const [search, setSearch] = useState('');
  const deferred = useDeferredValue(search.trim());
  const offers = useOffers(deferred);

  const renderItem = ({ item }: { item: Offer }) => (
    <Pressable accessibilityRole="button" accessibilityLabel={`${item.product_name}, ${item.status}`} onPress={() => router.push(`/products/${item.id}`)}>
      <Card style={styles.row}>
        <View style={[styles.icon, { backgroundColor: theme.infoSoft }]}>
          <Ionicons name="cube" size={20} color={theme.primary} />
        </View>
        <View style={{ flex: 1, gap: 2 }}>
          <Text variant="bodyStrong" tone="heading" numberOfLines={1}>
            {item.product_name}
          </Text>
          <Text variant="caption" tone="muted" numberOfLines={1}>
            {[item.affiliate_network, `${item.generations_count ?? 0} pieces`, relativeTime(item.created_at)].filter(Boolean).join(' · ')}
          </Text>
        </View>
        <StatusBadge status={item.status} />
      </Card>
    </Pressable>
  );

  return (
    <SafeAreaView edges={['top']} style={{ flex: 1, backgroundColor: theme.background }}>
      <FlatList
        data={offers.data ?? []}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderItem}
        contentContainerStyle={styles.list}
        ItemSeparatorComponent={() => <View style={{ height: Spacing.sm }} />}
        onEndReached={() => offers.hasNextPage && offers.fetchNextPage()}
        refreshControl={<RefreshControl refreshing={offers.isRefetching} onRefresh={() => void offers.refetch()} tintColor={theme.primary} />}
        ListHeaderComponent={
          <View style={styles.header}>
            <View style={styles.titleRow}>
              <Text variant="title" accessibilityRole="header" style={{ flex: 1 }}>
                Products
              </Text>
              <Button label="Add" icon="add" onPress={() => router.push('/products/new')} />
            </View>
            <SearchBox placeholder="Search products" value={search} onChangeText={setSearch} />
          </View>
        }
        ListEmptyComponent={
          offers.isPending ? (
            <ActivityIndicator color={theme.primary} />
          ) : offers.isError ? (
            <ErrorCard message={offers.error.message} onRetry={() => offers.refetch()} />
          ) : (
            <EmptyState
              icon="cube-outline"
              title={deferred ? 'No products match' : 'Add your first product'}
              message={deferred ? 'Try a different name.' : 'Paste a product link and AffilStack researches who buys it and how to sell it.'}
            />
          )
        }
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  list: { padding: Spacing.lg, paddingBottom: Spacing.xxl, width: '100%', maxWidth: MaxContentWidth, alignSelf: 'center' },
  header: { gap: Spacing.md, marginBottom: Spacing.lg },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md, padding: Spacing.md },
  icon: { width: 40, height: 40, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
});
