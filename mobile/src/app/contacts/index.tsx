import { router } from 'expo-router';
import { useDeferredValue, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import { Card } from '@/components/card';
import { Chip, EmptyState, ErrorCard, SearchBox, StatusBadge } from '@/components/kit';
import { Text } from '@/components/text';
import { MaxContentWidth, Palette, Spacing } from '@/constants/theme';
import { useContacts } from '@/hooks/use-core';
import { useTheme } from '@/hooks/use-theme';
import type { Contact, ContactStatus } from '@/lib/types';

const FILTERS: { label: string; value?: ContactStatus }[] = [
  { label: 'All' },
  { label: 'New', value: 'new' },
  { label: 'Contacted', value: 'contacted' },
  { label: 'Qualified', value: 'qualified' },
  { label: 'Customers', value: 'customer' },
];

/** CRM contacts: search by name, company or email; filter by status. */
export default function ContactsScreen() {
  const theme = useTheme();
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState<ContactStatus | undefined>();
  const contacts = useContacts(useDeferredValue(search.trim()), status);

  const renderItem = ({ item }: { item: Contact }) => (
    <Pressable accessibilityRole="button" onPress={() => router.push(`/contacts/${item.id}`)}>
      <Card style={styles.row}>
        <View style={[styles.avatar, { backgroundColor: Palette.navy900 }]}>
          <Text variant="bodyStrong" style={{ color: Palette.gold400 }}>
            {item.name.slice(0, 1).toUpperCase()}
          </Text>
        </View>
        <View style={{ flex: 1, gap: 2 }}>
          <Text variant="bodyStrong" tone="heading" numberOfLines={1}>
            {item.name}
          </Text>
          <Text variant="caption" tone="muted" numberOfLines={1}>
            {[item.company, item.location].filter(Boolean).join(' · ') || item.email || '—'}
          </Text>
        </View>
        <StatusBadge status={item.status} />
      </Card>
    </Pressable>
  );

  return (
    <FlatList
      style={{ backgroundColor: theme.background }}
      data={contacts.data ?? []}
      keyExtractor={(item) => String(item.id)}
      renderItem={renderItem}
      contentContainerStyle={styles.list}
      ItemSeparatorComponent={() => <View style={{ height: Spacing.sm }} />}
      onEndReached={() => contacts.hasNextPage && contacts.fetchNextPage()}
      refreshControl={<RefreshControl refreshing={contacts.isRefetching} onRefresh={() => void contacts.refetch()} tintColor={theme.primary} />}
      ListHeaderComponent={
        <View style={{ gap: Spacing.md, marginBottom: Spacing.lg }}>
          <SearchBox placeholder="Search name, company or email" value={search} onChangeText={setSearch} />
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: Spacing.sm }}>
            {FILTERS.map((filter) => (
              <Chip key={filter.label} label={filter.label} selected={status === filter.value} onPress={() => setStatus(filter.value)} />
            ))}
          </ScrollView>
        </View>
      }
      ListEmptyComponent={
        contacts.isPending ? (
          <ActivityIndicator color={theme.primary} />
        ) : contacts.isError ? (
          <ErrorCard message={contacts.error.message} onRetry={() => contacts.refetch()} />
        ) : (
          <EmptyState icon="people-outline" title="No contacts found" message="Contacts from lead runs, imports and the Chrome extension show up here." />
        )
      }
    />
  );
}

const styles = StyleSheet.create({
  list: { padding: Spacing.lg, paddingBottom: Spacing.xxl, width: '100%', maxWidth: MaxContentWidth, alignSelf: 'center' },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md, padding: Spacing.md },
  avatar: { width: 40, height: 40, borderRadius: 20, alignItems: 'center', justifyContent: 'center' },
});
