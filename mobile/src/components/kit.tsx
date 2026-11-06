import Ionicons from '@expo/vector-icons/Ionicons';
import type { ReactNode } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, TextInput, View, type TextInputProps } from 'react-native';

import { Card } from '@/components/card';
import { Text } from '@/components/text';
import { Fonts, Radius, Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

/** Small selectable pill (filters, content types, statuses). */
export function Chip({ label, selected, onPress, icon }: { label: string; selected?: boolean; onPress?: () => void; icon?: keyof typeof Ionicons.glyphMap }) {
  const theme = useTheme();

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected }}
      onPress={onPress}
      style={[styles.chip, { backgroundColor: selected ? theme.primary : theme.card, borderColor: selected ? theme.primary : theme.border }]}>
      {icon ? <Ionicons name={icon} size={14} color={selected ? theme.primaryText : theme.textMuted} /> : null}
      <Text variant="label" style={{ color: selected ? theme.primaryText : theme.text }}>
        {label}
      </Text>
    </Pressable>
  );
}

const STATUS: Record<string, { label: string; tone: 'info' | 'success' | 'warning' | 'danger' | 'muted' }> = {
  queued: { label: 'Queued', tone: 'info' },
  processing: { label: 'Working on it', tone: 'info' },
  researching: { label: 'Researching', tone: 'info' },
  completed: { label: 'Ready', tone: 'success' },
  ready: { label: 'Ready', tone: 'success' },
  failed: { label: 'Failed', tone: 'danger' },
  new: { label: 'New', tone: 'info' },
  contacted: { label: 'Contacted', tone: 'warning' },
  qualified: { label: 'Qualified', tone: 'success' },
  customer: { label: 'Customer', tone: 'success' },
  unqualified: { label: 'Not a fit', tone: 'muted' },
};

export function StatusBadge({ status }: { status: string }) {
  const theme = useTheme();
  const entry = STATUS[status] ?? { label: status, tone: 'muted' as const };
  const colors = {
    info: [theme.infoSoft, theme.infoText],
    success: [theme.successSoft, theme.success],
    warning: [theme.warningSoft, theme.warningText],
    danger: [theme.dangerSoft, theme.danger],
    muted: [theme.cardMuted, theme.textMuted],
  }[entry.tone];
  const working = status === 'queued' || status === 'processing' || status === 'researching';

  return (
    <View style={[styles.badge, { backgroundColor: colors[0] }]} accessibilityLabel={`Status: ${entry.label}`}>
      {working ? <ActivityIndicator size="small" color={colors[1]} style={{ transform: [{ scale: 0.7 }] }} /> : null}
      <Text variant="label" style={{ color: colors[1] }}>
        {entry.label}
      </Text>
    </View>
  );
}

export function SearchBox(props: TextInputProps) {
  const theme = useTheme();

  return (
    <View style={[styles.search, { backgroundColor: theme.card, borderColor: theme.border }]}>
      <Ionicons name="search" size={18} color={theme.textFaint} />
      <TextInput
        placeholderTextColor={theme.textFaint}
        returnKeyType="search"
        autoCorrect={false}
        clearButtonMode="while-editing"
        accessibilityLabel={props.placeholder}
        {...props}
        style={[styles.searchInput, { color: theme.text }]}
      />
    </View>
  );
}

export function Field({ label, ...props }: TextInputProps & { label: string }) {
  const theme = useTheme();

  return (
    <View style={{ gap: Spacing.xs }}>
      <Text variant="label" tone="muted">
        {label}
      </Text>
      <TextInput
        placeholderTextColor={theme.textFaint}
        accessibilityLabel={label}
        {...props}
        style={[styles.field, props.multiline && styles.multiline, { borderColor: theme.border, color: theme.text, backgroundColor: theme.card }, props.style]}
      />
    </View>
  );
}

export function EmptyState({ icon, title, message, action }: { icon: keyof typeof Ionicons.glyphMap; title: string; message: string; action?: ReactNode }) {
  const theme = useTheme();

  return (
    <Card style={styles.empty}>
      <View style={[styles.emptyIcon, { backgroundColor: theme.infoSoft }]}>
        <Ionicons name={icon} size={26} color={theme.primary} />
      </View>
      <Text variant="subheading" style={{ textAlign: 'center' }}>
        {title}
      </Text>
      <Text tone="muted" style={{ textAlign: 'center' }}>
        {message}
      </Text>
      {action}
    </Card>
  );
}

export function ErrorCard({ message, onRetry }: { message?: string; onRetry: () => void }) {
  const theme = useTheme();

  return (
    <EmptyState
      icon="cloud-offline-outline"
      title="Something went wrong"
      message={message ?? 'Check your connection and try again.'}
      action={
        <Pressable accessibilityRole="button" onPress={onRetry}>
          <Text variant="bodyStrong" style={{ color: theme.primary }}>
            Try again
          </Text>
        </Pressable>
      }
    />
  );
}

/** "Blog article", "LinkedIn post"… for a generation's module key. */
export function moduleLabel(module: string): string {
  return (
    {
      blog_article: 'Blog article',
      linkedin_post: 'LinkedIn post',
      linkedin_article: 'LinkedIn article',
      x_thread: 'X thread',
      youtube_script: 'Video script',
      ugc_video: 'Video',
    }[module] ?? module.replace(/_/g, ' ')
  );
}

export function videoStyleLabel(style: string | null): string {
  return { talking_review: 'Talking review', product_showcase: 'Product showcase', problem_solution: 'Problem → solution' }[style ?? ''] ?? 'Video';
}

const styles = StyleSheet.create({
  chip: { flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: Spacing.md, paddingVertical: 8, borderRadius: Radius.pill, borderWidth: 1, minHeight: 36 },
  badge: { flexDirection: 'row', alignItems: 'center', gap: 2, paddingHorizontal: 10, paddingVertical: 4, borderRadius: Radius.pill, alignSelf: 'flex-start' },
  search: { flexDirection: 'row', alignItems: 'center', gap: Spacing.sm, borderWidth: 1, borderRadius: Radius.md, paddingHorizontal: Spacing.md, minHeight: 46 },
  searchInput: { flex: 1, fontFamily: Fonts.body, fontSize: 15, paddingVertical: 10 },
  field: { borderWidth: 1, borderRadius: Radius.md, paddingHorizontal: Spacing.md, minHeight: 48, fontFamily: Fonts.body, fontSize: 15 },
  multiline: { minHeight: 110, paddingTop: 12, textAlignVertical: 'top' },
  empty: { alignItems: 'center', gap: Spacing.md, paddingVertical: Spacing.xl },
  emptyIcon: { width: 56, height: 56, borderRadius: 18, alignItems: 'center', justifyContent: 'center' },
});
