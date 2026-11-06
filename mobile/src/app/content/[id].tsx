import { Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { ErrorCard, moduleLabel, StatusBadge } from '@/components/kit';
import { Screen } from '@/components/screen';
import { Text } from '@/components/text';
import { Spacing } from '@/constants/theme';
import { useGeneration } from '@/hooks/use-core';
import { useTheme } from '@/hooks/use-theme';
import { copyText, shareText } from '@/lib/share';

/** A piece of AI content: read it, copy it, or share it to any app. */
export default function ContentScreen() {
  const theme = useTheme();
  const id = Number(useLocalSearchParams<{ id: string }>().id);
  const generation = useGeneration(id);
  const [copied, setCopied] = useState(false);

  if (generation.isPending) return <ActivityIndicator style={{ marginTop: Spacing.xxl }} color={theme.primary} />;
  if (generation.isError || !generation.data) return <Screen edges={[]}><ErrorCard message={generation.error?.message} onRetry={() => generation.refetch()} /></Screen>;

  const item = generation.data;
  const text = item.plain_text ?? '';

  const copy = async () => {
    await copyText(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <Screen edges={['bottom']} onRefresh={() => generation.refetch()} refreshing={generation.isRefetching}>
      <Stack.Screen options={{ title: moduleLabel(item.module) }} />
      <View style={styles.meta}>
        <StatusBadge status={item.status} />
        {item.offer_name ? (
          <Text variant="caption" tone="muted">
            {item.offer_name}
          </Text>
        ) : null}
      </View>

      {item.status === 'completed' ? (
        <>
          {item.title ? <Text variant="heading">{item.title}</Text> : null}
          <Card>
            <Text selectable>{text}</Text>
          </Card>
          <View style={styles.actions}>
            <View style={{ flex: 1 }}>
              <Button label={copied ? 'Copied' : 'Copy'} icon={copied ? 'checkmark' : 'copy-outline'} variant="secondary" onPress={copy} />
            </View>
            <View style={{ flex: 1 }}>
              <Button label="Share" icon="share-outline" onPress={() => shareText(text, item.title ?? undefined)} />
            </View>
          </View>
        </>
      ) : item.status === 'failed' ? (
        <Card>
          <Text tone="danger">{item.error ?? 'This one failed. Your credits were not used — try again from the product.'}</Text>
        </Card>
      ) : (
        <Card style={{ gap: Spacing.sm }}>
          <Text variant="subheading">Writing it now…</Text>
          <Text tone="muted">This usually takes under a minute. You can leave this screen — it will be waiting in the product's content.</Text>
        </Card>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  meta: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  actions: { flexDirection: 'row', gap: Spacing.md },
});
