import { Stack, useLocalSearchParams } from 'expo-router';
import { useVideoPlayer, VideoView } from 'expo-video';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { Button } from '@/components/button';
import { ErrorCard, videoStyleLabel } from '@/components/kit';
import { Screen } from '@/components/screen';
import { Text } from '@/components/text';
import { Radius, Spacing } from '@/constants/theme';
import { useGeneration } from '@/hooks/use-core';
import { useTheme } from '@/hooks/use-theme';
import { saveVideo, shareVideo } from '@/lib/share';

function Player({ url }: { url: string }) {
  const player = useVideoPlayer(url, (instance) => {
    instance.loop = true;
  });

  return <VideoView player={player} style={styles.video} contentFit="contain" nativeControls allowsPictureInPicture accessibilityLabel="Your video" />;
}

/** Watch a finished video, save it to Photos, or share the file to TikTok, Instagram, YouTube or WhatsApp. */
export default function VideoScreen() {
  const theme = useTheme();
  const id = Number(useLocalSearchParams<{ id: string }>().id);
  const video = useGeneration(id);
  const [busy, setBusy] = useState<'save' | 'share' | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  if (video.isPending) return <ActivityIndicator style={{ marginTop: Spacing.xxl }} color={theme.primary} />;
  if (video.isError || !video.data) return <Screen edges={[]}><ErrorCard message={video.error?.message} onRetry={() => video.refetch()} /></Screen>;

  const item = video.data;

  const run = async (action: 'save' | 'share') => {
    if (!item.video_url) return;
    setBusy(action);
    setMessage(null);

    try {
      if (action === 'share') {
        await shareVideo(item.video_url, item.id);
      } else {
        setMessage((await saveVideo(item.video_url, item.id)) ? 'Saved to your Photos.' : 'Allow Photos access in Settings to save videos.');
      }
    } catch {
      setMessage('Could not download the video. Check your connection and try again.');
    } finally {
      setBusy(null);
    }
  };

  return (
    <Screen edges={['bottom']}>
      <Stack.Screen options={{ title: item.offer_name ?? 'Video' }} />
      {item.video_url ? <Player url={item.video_url} /> : <Text tone="muted">This video isn't ready yet.</Text>}
      <Text variant="caption" tone="muted">
        {videoStyleLabel(item.video_style)}
      </Text>
      <View style={styles.actions}>
        <View style={{ flex: 1 }}>
          <Button label="Save" icon="download-outline" variant="secondary" loading={busy === 'save'} disabled={!item.video_url} onPress={() => run('save')} />
        </View>
        <View style={{ flex: 1 }}>
          <Button label="Share" icon="share-social-outline" loading={busy === 'share'} disabled={!item.video_url} onPress={() => run('share')} />
        </View>
      </View>
      <Text variant="caption" tone="faint">
        Share opens your phone's share sheet: pick TikTok, Instagram, YouTube or WhatsApp.
      </Text>
      {message ? (
        <Text variant="caption" tone="muted" accessibilityRole="alert">
          {message}
        </Text>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  video: { width: '100%', aspectRatio: 9 / 16, maxHeight: 560, borderRadius: Radius.lg, backgroundColor: '#000', alignSelf: 'center' },
  actions: { flexDirection: 'row', gap: Spacing.md },
});
