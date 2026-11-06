import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';

import { AnnouncementBanner } from '@/components/announcement-banner';
import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Screen } from '@/components/screen';
import { StatTile } from '@/components/stat-tile';
import { Text } from '@/components/text';
import { Palette, Radius, Spacing } from '@/constants/theme';
import { useAppConfig, useHome } from '@/hooks/use-api';
import { useTheme } from '@/hooks/use-theme';
import { daysUntil, formatDate, greeting } from '@/lib/format';
import type { Plan } from '@/lib/types';

function planLine(plan: Plan | null): string {
  if (!plan) return 'No active plan';
  if (plan.is_trial) {
    const days = daysUntil(plan.trial_ends_at);

    return `Free trial · ${days === 0 ? 'ends today' : `${days} day${days === 1 ? '' : 's'} left`}`;
  }
  if (plan.ends_at) return `Ends ${formatDate(plan.ends_at)}`;
  if (plan.renews_at) return `Renews ${formatDate(plan.renews_at)}`;

  return plan.status === 'active' ? 'Active' : plan.status;
}

/**
 * Home: what AffilStack has done for you, your plan, credits and wallet,
 * and anything your AI agents need you to approve. Balances only — buying
 * and top-ups stay on the website (version 1 payment decision).
 */
export default function HomeScreen() {
  const theme = useTheme();
  const config = useAppConfig();
  const home = useHome();

  if (home.isPending) {
    return (
      <Screen scroll={false}>
        <ActivityIndicator style={{ marginTop: Spacing.xxl }} color={theme.primary} accessibilityLabel="Loading" />
      </Screen>
    );
  }

  if (home.isError || !home.data) {
    return (
      <Screen>
        <Card>
          <Text variant="subheading">We couldn't load your dashboard</Text>
          <Text tone="muted" style={{ marginVertical: Spacing.sm }}>
            {home.error?.message ?? 'Check your connection and try again.'}
          </Text>
          <Button label="Try again" onPress={() => home.refetch()} />
        </Card>
      </Screen>
    );
  }

  const { account, wallet, progress, agents } = home.data;
  const firstName = account.name.split(' ')[0];

  return (
    <Screen onRefresh={() => Promise.all([home.refetch(), config.refetch()])} refreshing={home.isRefetching}>
      <View style={styles.header}>
        <View style={{ flex: 1 }}>
          <Text variant="caption" tone="muted">
            {greeting()},
          </Text>
          <Text variant="title" accessibilityRole="header">
            {firstName}
          </Text>
        </View>
        <View style={[styles.avatar, { backgroundColor: Palette.navy900 }]} accessibilityLabel={account.name}>
          <Text variant="bodyStrong" style={{ color: Palette.gold400 }}>
            {account.initials}
          </Text>
        </View>
      </View>

      <AnnouncementBanner announcement={config.data?.announcement ?? null} />

      <Pressable accessibilityRole="button" accessibilityLabel="Plan and credits" onPress={() => router.push('/billing')}>
        <Card style={[styles.planCard, { backgroundColor: Palette.navy900, borderColor: Palette.navy800 }]}>
          <View style={styles.planTop}>
            <View style={{ flex: 1 }}>
              <Text variant="label" style={{ color: Palette.gold300, textTransform: 'uppercase', letterSpacing: 0.8 }}>
                {account.plan?.name ? `${account.plan.name} plan` : 'Your plan'}
              </Text>
              <Text variant="subheading" style={{ color: '#ffffff', marginTop: 2 }}>
                {planLine(account.plan)}
              </Text>
            </View>
            <Ionicons name="shield-checkmark" size={26} color={Palette.gold400} />
          </View>
          <View style={styles.balances}>
            <View style={styles.balance}>
              <Text variant="caption" style={{ color: '#aab3cc' }}>
                Credits
              </Text>
              <Text variant="heading" style={{ color: '#ffffff' }}>
                {account.credits_balance.toLocaleString()}
              </Text>
            </View>
            <View style={[styles.divider, { backgroundColor: Palette.navy700 }]} />
            <View style={styles.balance}>
              <Text variant="caption" style={{ color: '#aab3cc' }}>
                Wallet
              </Text>
              <Text variant="heading" style={{ color: '#ffffff' }}>
                {wallet.formatted}
              </Text>
            </View>
          </View>
        </Card>
      </Pressable>

      {agents.pending_approvals > 0 ? (
        <Pressable accessibilityRole="button" onPress={() => router.navigate('/(tabs)/agents')}>
          <Card style={styles.agentCard}>
            <View style={[styles.agentIcon, { backgroundColor: theme.infoSoft }]}>
              <Ionicons name="sparkles" size={20} color={theme.primary} />
            </View>
            <View style={{ flex: 1 }}>
              <Text variant="bodyStrong" tone="heading">
                {agents.pending_approvals} suggestion{agents.pending_approvals === 1 ? '' : 's'} to approve
              </Text>
              <Text variant="caption" tone="muted">
                Your AI agents drafted replies and actions for you.
              </Text>
            </View>
            <Ionicons name="chevron-forward" size={18} color={theme.textFaint} />
          </Card>
        </Pressable>
      ) : null}

      <View style={{ gap: Spacing.sm }}>
        <Text variant="heading" accessibilityRole="header">
          What AffilStack has done for you
        </Text>
        <View style={[styles.progressTrack, { backgroundColor: theme.border }]} accessibilityLabel={`${progress.milestone.percent}% of the way to ${progress.milestone.next.toLocaleString()}`}>
          <View style={[styles.progressFill, { width: `${progress.milestone.percent}%`, backgroundColor: Palette.gold400 }]} />
        </View>
        <Text variant="caption" tone="muted">
          {progress.total.toLocaleString()} things done · next milestone {progress.milestone.next.toLocaleString()}
        </Text>
      </View>

      <View style={styles.grid}>
        {progress.tiles.map((tile) => (
          <StatTile key={tile.key} tile={tile} />
        ))}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  avatar: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center' },
  planCard: { gap: Spacing.lg },
  planTop: { flexDirection: 'row', alignItems: 'flex-start', gap: Spacing.md },
  balances: { flexDirection: 'row', alignItems: 'stretch' },
  balance: { flex: 1, gap: 2 },
  divider: { width: 1, marginHorizontal: Spacing.lg },
  agentCard: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  agentIcon: { width: 40, height: 40, borderRadius: Radius.md, alignItems: 'center', justifyContent: 'center' },
  progressTrack: { height: 8, borderRadius: Radius.pill, overflow: 'hidden' },
  progressFill: { height: '100%', borderRadius: Radius.pill },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.md },
});
