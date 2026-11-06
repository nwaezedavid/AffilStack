import Ionicons from '@expo/vector-icons/Ionicons';
import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Chip, ErrorCard, Field, moduleLabel, StatusBadge, videoStyleLabel } from '@/components/kit';
import { Screen } from '@/components/screen';
import { Text } from '@/components/text';
import { Spacing } from '@/constants/theme';
import { useCreateContent, useOffer } from '@/hooks/use-core';
import { useTheme } from '@/hooks/use-theme';
import { ApiError } from '@/lib/api';
import { relativeTime } from '@/lib/format';

/** One product: research, its content and "Create content". */
export default function ProductScreen() {
  const theme = useTheme();
  const id = Number(useLocalSearchParams<{ id: string }>().id);
  const offer = useOffer(id);
  const create = useCreateContent(id);
  const [type, setType] = useState<string | null>(null);
  const [keyword, setKeyword] = useState('');

  if (offer.isPending) return <ActivityIndicator style={{ marginTop: Spacing.xxl }} color={theme.primary} />;
  if (offer.isError || !offer.data) return <Screen edges={[]}><ErrorCard message={offer.error?.message} onRetry={() => offer.refetch()} /></Screen>;

  const { offer: product, content, content_types: types } = offer.data;
  const selected = types.find((item) => item.type === type);
  const research = product.research;

  const submit = () => {
    if (!selected) return;

    create.mutate(
      { type: selected.type, keyword: selected.type === 'blog_article' && keyword.trim() ? keyword.trim() : undefined },
      { onSuccess: (result) => { setType(null); setKeyword(''); router.push(`/content/${result.data.id}`); } },
    );
  };

  return (
    <Screen edges={[]} onRefresh={() => offer.refetch()} refreshing={offer.isRefetching}>
      <Stack.Screen options={{ title: product.product_name }} />

      <View style={styles.header}>
        <StatusBadge status={product.status} />
        <Text variant="caption" tone="muted">
          {product.affiliate_network}
        </Text>
      </View>

      {research && (research.ideal_customer || research.recommended_angle) ? (
        <Card style={{ gap: Spacing.md }}>
          <Text variant="subheading">Research</Text>
          {[
            ['person-outline', 'Who buys it', research.ideal_customer],
            ['location-outline', 'Where to find them', research.where_to_find],
            ['megaphone-outline', 'Best channel', research.recommended_channel],
            ['bulb-outline', 'Angle that sells', research.recommended_angle],
          ]
            .filter(([, , value]) => value)
            .map(([icon, label, value]) => (
              <View key={label} style={styles.fact}>
                <Ionicons name={icon as keyof typeof Ionicons.glyphMap} size={18} color={theme.primary} />
                <View style={{ flex: 1 }}>
                  <Text variant="label" tone="muted">
                    {label}
                  </Text>
                  <Text>{value}</Text>
                </View>
              </View>
            ))}
        </Card>
      ) : product.status !== 'ready' ? (
        <Card>
          <Text tone="muted">AffilStack is researching this product. This page updates by itself.</Text>
        </Card>
      ) : null}

      <Card style={{ gap: Spacing.md }}>
        <Text variant="subheading">Create content</Text>
        <View style={styles.chips}>
          {types.map((item) => (
            <Chip key={item.type} label={`${item.label} · ${item.credits} cr`} selected={type === item.type} onPress={item.available ? () => setType(item.type) : undefined} icon={item.available ? undefined : 'lock-closed'} />
          ))}
        </View>
        {selected ? (
          <>
            <Text variant="caption" tone="muted">
              {selected.description}
            </Text>
            {selected.type === 'blog_article' ? <Field label="Target keyword (optional)" value={keyword} onChangeText={setKeyword} placeholder="best AI note taking app" /> : null}
            {create.error ? (
              <Text variant="caption" tone="danger" accessibilityRole="alert">
                {create.error instanceof ApiError ? create.error.message : 'Something went wrong.'}
              </Text>
            ) : null}
            <Button label={`Create ${selected.label.toLowerCase()} (${selected.credits} credits)`} icon="sparkles" variant="gold" loading={create.isPending} onPress={submit} />
          </>
        ) : (
          <Text variant="caption" tone="faint">
            Pick what to write. Locked items aren't included in your plan.
          </Text>
        )}
      </Card>

      <Text variant="heading" accessibilityRole="header">
        Content
      </Text>
      {content.length === 0 ? <Text tone="muted">Nothing written for this product yet.</Text> : null}
      {content.map((item) => (
        <Pressable key={item.id} accessibilityRole="button" onPress={() => router.push(item.module === 'ugc_video' ? `/videos/${item.id}` : `/content/${item.id}`)}>
          <Card style={styles.contentRow}>
            <Ionicons name={item.module === 'ugc_video' ? 'videocam-outline' : 'document-text-outline'} size={20} color={theme.primary} />
            <View style={{ flex: 1, gap: 2 }}>
              <Text variant="bodyStrong" tone="heading" numberOfLines={1}>
                {item.title ?? (item.module === 'ugc_video' ? videoStyleLabel(item.video_style) : moduleLabel(item.module))}
              </Text>
              <Text variant="caption" tone="muted" numberOfLines={1}>
                {moduleLabel(item.module)} · {relativeTime(item.created_at)}
              </Text>
            </View>
            <StatusBadge status={item.status} />
          </Card>
        </Pressable>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  fact: { flexDirection: 'row', gap: Spacing.md, alignItems: 'flex-start' },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.sm },
  contentRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md, padding: Spacing.md },
});
