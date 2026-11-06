import Ionicons from '@expo/vector-icons/Ionicons';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { Card } from '@/components/card';
import { ErrorCard } from '@/components/kit';
import { Screen } from '@/components/screen';
import { Text } from '@/components/text';
import { Palette, Spacing } from '@/constants/theme';
import { useBilling } from '@/hooks/use-core';
import { useTheme } from '@/hooks/use-theme';
import { daysUntil, formatDate, relativeTime } from '@/lib/format';
import type { Plan } from '@/lib/types';

function planStatus(plan: Plan | null): string {
  if (!plan) return 'No active plan';
  if (plan.is_trial) {
    const days = daysUntil(plan.trial_ends_at);

    return days > 0 ? `Trial · ${days} day${days === 1 ? '' : 's'} left` : 'Trial ending today';
  }
  if (plan.ends_at) return `Ends ${formatDate(plan.ends_at)}`;
  if (plan.renews_at) return `Renews ${formatDate(plan.renews_at)}`;

  return plan.status === 'active' ? 'Active' : plan.status;
}

/**
 * More > Plan & credits: view only. Shows the plan, balances and recent
 * activity — and deliberately has no buy, upgrade or top-up buttons or
 * links (App Store and Google Play rules for apps whose purchases happen
 * elsewhere).
 */
export default function BillingScreen() {
  const theme = useTheme();
  const billing = useBilling();

  if (billing.isPending) {
    return (
      <Screen edges={[]}>
        <ActivityIndicator color={theme.primary} />
      </Screen>
    );
  }

  if (billing.isError || !billing.data) {
    return (
      <Screen edges={[]}>
        <ErrorCard onRetry={() => billing.refetch()} />
      </Screen>
    );
  }

  const { account, credits, wallet, shows_history: showsHistory, credit_activity: creditActivity, wallet_activity: walletActivity } = billing.data;

  return (
    <Screen edges={[]} onRefresh={() => billing.refetch()} refreshing={billing.isRefetching}>
      <Card style={[styles.planCard, { backgroundColor: Palette.navy900, borderColor: Palette.navy800 }]}>
        <Text variant="label" style={{ color: Palette.gold300, textTransform: 'uppercase', letterSpacing: 0.8 }}>
          {account.plan?.name ? `${account.plan.name} plan` : 'Your plan'}
        </Text>
        <Text variant="subheading" style={{ color: '#ffffff' }}>
          {planStatus(account.plan)}
        </Text>
        {credits.monthly_allowance !== null ? (
          <Text variant="caption" style={{ color: '#aab3cc' }}>
            Includes {credits.monthly_allowance.toLocaleString()} credits a month
          </Text>
        ) : null}
        {account.is_seat ? (
          <Text variant="caption" style={{ color: '#aab3cc' }}>
            You're on your team owner's plan.
          </Text>
        ) : null}
      </Card>

      <View style={styles.tiles}>
        <Card style={styles.tile}>
          <Ionicons name="flash-outline" size={20} color={theme.primary} />
          <Text variant="caption" tone="muted">
            Credits
          </Text>
          <Text variant="heading">{credits.balance.toLocaleString()}</Text>
        </Card>
        <Card style={styles.tile}>
          <Ionicons name="wallet-outline" size={20} color={theme.primary} />
          <Text variant="caption" tone="muted">
            Wallet
          </Text>
          <Text variant="heading">{wallet.formatted}</Text>
        </Card>
      </View>

      {showsHistory ? (
        <>
          <Card>
            <Text variant="label" tone="faint" style={styles.section}>
              RECENT CREDIT ACTIVITY
            </Text>
            {creditActivity.length === 0 ? (
              <Text variant="caption" tone="muted">
                Nothing yet.
              </Text>
            ) : null}
            {creditActivity.map((entry, index) => (
              <View key={entry.id} style={[styles.row, index > 0 && { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.border }]}>
                <View style={{ flex: 1 }}>
                  <Text variant="bodyStrong" tone="heading">
                    {entry.label}
                  </Text>
                  <Text variant="caption" tone="muted">
                    {relativeTime(entry.created_at)} · balance {entry.balance_after.toLocaleString()}
                  </Text>
                </View>
                <Text variant="bodyStrong" tone={entry.amount >= 0 ? 'success' : 'heading'}>
                  {entry.amount >= 0 ? '+' : '−'}
                  {Math.abs(entry.amount).toLocaleString()}
                </Text>
              </View>
            ))}
          </Card>

          {walletActivity.length > 0 ? (
            <Card>
              <Text variant="label" tone="faint" style={styles.section}>
                WALLET ACTIVITY
              </Text>
              {walletActivity.map((entry, index) => (
                <View key={entry.id} style={[styles.row, index > 0 && { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: theme.border }]}>
                  <View style={{ flex: 1 }}>
                    <Text variant="bodyStrong" tone="heading">
                      {entry.label}
                    </Text>
                    <Text variant="caption" tone="muted">
                      {relativeTime(entry.created_at)}
                    </Text>
                  </View>
                  <Text variant="bodyStrong" tone={entry.amount_minor >= 0 ? 'success' : 'heading'}>
                    {entry.amount_minor >= 0 ? '+' : ''}
                    {entry.formatted}
                  </Text>
                </View>
              ))}
            </Card>
          ) : null}
        </>
      ) : (
        <Text variant="caption" tone="muted">
          Your team owner's credit history is only visible to them.
        </Text>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  planCard: { gap: Spacing.xs },
  tiles: { flexDirection: 'row', gap: Spacing.md },
  tile: { flex: 1, gap: Spacing.xs },
  section: { letterSpacing: 0.8, marginBottom: Spacing.xs },
  row: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md, paddingVertical: Spacing.sm },
});
