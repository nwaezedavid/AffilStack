import { router } from 'expo-router';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';

import { Card } from '@/components/card';
import { EmptyState, ErrorCard } from '@/components/kit';
import { Screen } from '@/components/screen';
import { Text } from '@/components/text';
import { Palette, Spacing } from '@/constants/theme';
import { useInbox } from '@/hooks/use-core';
import { useTheme } from '@/hooks/use-theme';
import { relativeTime } from '@/lib/format';

/** Inbox & replies: contacts who wrote back, unread first. */
export default function InboxScreen() {
  const theme = useTheme();
  const inbox = useInbox();

  return (
    <Screen edges={[]} onRefresh={() => inbox.refetch()} refreshing={inbox.isRefetching}>
      {inbox.isPending ? <ActivityIndicator color={theme.primary} /> : null}
      {inbox.isError ? <ErrorCard message={inbox.error.message} onRetry={() => inbox.refetch()} /> : null}
      {inbox.data && !inbox.data.email_connected ? (
        <Card muted>
          <Text variant="caption" tone="muted">
            Connect your email on the website (Email Sending) so you can answer from your own address.
          </Text>
        </Card>
      ) : null}
      {inbox.data?.data.length === 0 ? <EmptyState icon="mail-open-outline" title="No replies yet" message="When a contact answers one of your emails, it lands here." /> : null}
      {inbox.data?.data.map((item) => (
        <Pressable key={item.contact_id} accessibilityRole="button" accessibilityLabel={`${item.contact_name}, ${item.unread_count} unread`} onPress={() => router.push(`/inbox/${item.contact_id}`)}>
          <Card style={styles.row}>
            <View style={[styles.avatar, { backgroundColor: Palette.navy900 }]}>
              <Text variant="bodyStrong" style={{ color: Palette.gold400 }}>
                {(item.contact_name ?? '?').slice(0, 1).toUpperCase()}
              </Text>
            </View>
            <View style={{ flex: 1, gap: 2 }}>
              <View style={styles.top}>
                <Text variant="bodyStrong" tone="heading" numberOfLines={1} style={{ flex: 1 }}>
                  {item.contact_name ?? 'Unknown'}
                </Text>
                <Text variant="caption" tone="faint">
                  {relativeTime(item.received_at)}
                </Text>
              </View>
              <Text variant="caption" tone="muted" numberOfLines={2}>
                {item.snippet}
              </Text>
              {item.has_suggestion ? (
                <Text variant="label" tone="accent">
                  ✦ Suggested reply ready
                </Text>
              ) : null}
            </View>
            {item.unread_count > 0 ? (
              <View style={[styles.unread, { backgroundColor: theme.primary }]}>
                <Text variant="label" style={{ color: '#fff' }}>
                  {item.unread_count}
                </Text>
              </View>
            ) : null}
          </Card>
        </Pressable>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md, padding: Spacing.md },
  avatar: { width: 40, height: 40, borderRadius: 20, alignItems: 'center', justifyContent: 'center' },
  top: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm },
  unread: { minWidth: 22, height: 22, borderRadius: 11, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 6 },
});
