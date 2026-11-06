import Ionicons from '@expo/vector-icons/Ionicons';
import { ActivityIndicator, StyleSheet, View } from 'react-native';
import { useState } from 'react';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { EmptyState, ErrorCard } from '@/components/kit';
import { Screen } from '@/components/screen';
import { Text } from '@/components/text';
import { Spacing } from '@/constants/theme';
import { useAgentDecision, useAgents } from '@/hooks/use-core';
import { useTheme } from '@/hooks/use-theme';
import { ApiError } from '@/lib/api';
import { relativeTime } from '@/lib/format';
import type { AgentActivity } from '@/lib/types';

const AGENT_ICONS: Record<string, keyof typeof Ionicons.glyphMap> = {
  reply_copilot: 'chatbubbles-outline',
  lead_hunter: 'people-outline',
  commission_watchdog: 'shield-checkmark-outline',
  content_autopilot: 'create-outline',
  offer_scout: 'compass-outline',
  weekly_briefing: 'newspaper-outline',
};

/**
 * AI agents: approve or dismiss what they suggest, see what they did and
 * this week's briefing. Reply Copilot's drafts are in the Inbox.
 */
export default function AgentsScreen() {
  const theme = useTheme();
  const agents = useAgents();
  const decide = useAgentDecision();
  const [message, setMessage] = useState<{ text: string; ok: boolean } | null>(null);

  if (agents.isPending) return <Screen><ActivityIndicator color={theme.primary} /></Screen>;
  if (agents.isError || !agents.data) return <Screen><ErrorCard message={agents.error?.message} onRetry={() => agents.refetch()} /></Screen>;

  const { approvals, activity, week, included } = agents.data;
  const thisWeek = Object.values(week).reduce((total, count) => total + Number(count), 0);

  const act = (item: AgentActivity, decision: 'approve' | 'dismiss') =>
    decide.mutate(
      { id: item.id, decision },
      {
        onSuccess: (result) => setMessage({ text: result.message, ok: true }),
        onError: (error) => setMessage({ text: error instanceof ApiError ? error.message : 'Something went wrong.', ok: false }),
      },
    );

  const icon = (agent: string) => <Ionicons name={AGENT_ICONS[agent] ?? 'sparkles-outline'} size={20} color={theme.primary} />;

  return (
    <Screen onRefresh={() => agents.refetch()} refreshing={agents.isRefetching}>
      <Text variant="title" accessibilityRole="header">
        AI agents
      </Text>
      <Text tone="muted">{included ? `${thisWeek} things done for you this week.` : 'AI agents aren\'t included in your current plan.'}</Text>

      {message ? (
        <Text variant="caption" tone={message.ok ? 'success' : 'danger'} accessibilityRole="alert">
          {message.text}
        </Text>
      ) : null}

      <Text variant="heading" accessibilityRole="header">
        Waiting for you ({approvals.length})
      </Text>
      {approvals.length === 0 ? <EmptyState icon="checkmark-done-outline" title="All caught up" message="When an agent needs your OK, it shows up here and we send you a notification." /> : null}
      {approvals.map((item) => (
        <Card key={item.id} style={{ gap: Spacing.md }}>
          <View style={styles.head}>
            {icon(item.agent)}
            <Text variant="label" tone="muted" style={{ flex: 1 }}>
              {item.agent_name.toUpperCase()} · {relativeTime(item.created_at)}
            </Text>
          </View>
          <Text>{item.summary}</Text>
          <View style={styles.buttons}>
            <View style={{ flex: 1 }}>
              <Button label="Dismiss" variant="secondary" disabled={decide.isPending} onPress={() => act(item, 'dismiss')} />
            </View>
            <View style={{ flex: 1 }}>
              <Button label={item.approve_label} icon="checkmark" loading={decide.isPending && decide.variables?.id === item.id && decide.variables.decision === 'approve'} onPress={() => act(item, 'approve')} />
            </View>
          </View>
        </Card>
      ))}

      <Text variant="heading" accessibilityRole="header">
        Activity
      </Text>
      {activity.length === 0 ? <Text tone="muted">Nothing yet — your agents report here as they work.</Text> : null}
      <Card style={{ gap: Spacing.lg }}>
        {activity.map((item) => (
          <View key={item.id} style={styles.feed}>
            {icon(item.agent)}
            <View style={{ flex: 1, gap: 2 }}>
              <Text variant="label" tone="muted">
                {item.agent_name} · {relativeTime(item.created_at)}
              </Text>
              <Text>{item.summary}</Text>
            </View>
          </View>
        ))}
      </Card>
    </Screen>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm },
  buttons: { flexDirection: 'row', gap: Spacing.md },
  feed: { flexDirection: 'row', gap: Spacing.md, alignItems: 'flex-start' },
});
