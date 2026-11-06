import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Chip, EmptyState, StatusBadge, videoStyleLabel } from '@/components/kit';
import { Screen } from '@/components/screen';
import { Text } from '@/components/text';
import { Radius, Spacing } from '@/constants/theme';
import { useCreateVideo, useOffers, useVideoStyles, useVideos } from '@/hooks/use-core';
import { useTheme } from '@/hooks/use-theme';
import { ApiError } from '@/lib/api';
import { relativeTime } from '@/lib/format';

type Photo = { uri: string; name: string; type: string };

/**
 * One-click videos: pick a product, a style (the same three as the
 * website) and optionally a product photo, press Create. Finished videos
 * play, save and share from here.
 */
export default function VideosScreen() {
  const theme = useTheme();
  const styles_ = useVideoStyles();
  const offers = useOffers('');
  const videos = useVideos();
  const create = useCreateVideo();
  const [offerId, setOfferId] = useState<number | null>(null);
  const [style, setStyle] = useState<string | null>(null);
  const [photo, setPhoto] = useState<Photo | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // Keep the list fresh while a video is still rendering.
  useEffect(() => {
    if (!videos.working) return;
    const timer = setInterval(() => videos.refetch(), 15000);

    return () => clearInterval(timer);
  }, [videos.working, videos]);

  const readyOffers = (offers.data ?? []).filter((offer) => offer.status === 'ready');
  const chosenStyle = styles_.data?.find((item) => item.style === style);

  const pick = async (source: 'camera' | 'library') => {
    const options: ImagePicker.ImagePickerOptions = { mediaTypes: ['images'], quality: 0.85, allowsEditing: true };

    if (source === 'camera') {
      const permission = await ImagePicker.requestCameraPermissionsAsync();
      if (!permission.granted) return setNotice('Allow camera access in Settings to take a product photo.');
    }

    const result = source === 'camera' ? await ImagePicker.launchCameraAsync(options) : await ImagePicker.launchImageLibraryAsync(options);

    if (!result.canceled && result.assets[0]) {
      const asset = result.assets[0];
      setPhoto({ uri: asset.uri, name: asset.fileName ?? 'product.jpg', type: asset.mimeType ?? 'image/jpeg' });
    }
  };

  const submit = () => {
    if (!offerId || !style) return;
    setNotice(null);

    create.mutate(
      { offerId, style, photo: chosenStyle?.uses_product_photo ? photo : null },
      {
        onSuccess: () => {
          setPhoto(null);
          setNotice('Your video is being created — usually 2 to 10 minutes. We\'ll let you know when it\'s ready.');
        },
      },
    );
  };

  return (
    <Screen onRefresh={() => Promise.all([videos.refetch(), styles_.refetch()])} refreshing={videos.isRefetching}>
      <Text variant="title" accessibilityRole="header">
        One-click videos
      </Text>

      <Card style={{ gap: Spacing.md }}>
        <Text variant="subheading">1. Product</Text>
        {offers.isPending ? <ActivityIndicator color={theme.primary} /> : null}
        {readyOffers.length === 0 && !offers.isPending ? <Text tone="muted">Add and research a product first (Products tab).</Text> : null}
        <View style={styles.chips}>
          {readyOffers.map((offer) => (
            <Chip key={offer.id} label={offer.product_name} selected={offerId === offer.id} onPress={() => setOfferId(offer.id)} />
          ))}
        </View>

        <Text variant="subheading">2. Style</Text>
        {(styles_.data ?? []).map((item) => (
          <Pressable
            key={item.style}
            accessibilityRole="radio"
            accessibilityState={{ selected: style === item.style, disabled: !item.available }}
            disabled={!item.available}
            onPress={() => setStyle(item.style)}
            style={[styles.style, { borderColor: style === item.style ? theme.primary : theme.border, backgroundColor: style === item.style ? theme.infoSoft : theme.card, opacity: item.available ? 1 : 0.5 }]}>
            <View style={{ flex: 1, gap: 2 }}>
              <Text variant="bodyStrong" tone="heading">
                {item.label}
              </Text>
              <Text variant="caption" tone="muted">
                {item.description}
              </Text>
              <Text variant="label" tone="accent">
                {item.credits} credits · about {item.duration_seconds}s{item.available ? '' : ' · unavailable right now'}
              </Text>
            </View>
            <Ionicons name={style === item.style ? 'radio-button-on' : 'radio-button-off'} size={22} color={theme.primary} />
          </Pressable>
        ))}

        {chosenStyle?.uses_product_photo ? (
          <>
            <Text variant="subheading">3. Product photo (optional)</Text>
            <Text variant="caption" tone="muted">
              We use the picture from the product's website unless you add your own.
            </Text>
            {photo ? <Image source={{ uri: photo.uri }} style={styles.photo} contentFit="cover" accessibilityLabel="Selected product photo" /> : null}
            <View style={styles.row}>
              <View style={{ flex: 1 }}>
                <Button label="Camera" icon="camera-outline" variant="secondary" onPress={() => pick('camera')} />
              </View>
              <View style={{ flex: 1 }}>
                <Button label="Gallery" icon="images-outline" variant="secondary" onPress={() => pick('library')} />
              </View>
            </View>
          </>
        ) : null}

        {create.error ? (
          <Text variant="caption" tone="danger" accessibilityRole="alert">
            {create.error instanceof ApiError ? create.error.message : 'Something went wrong.'}
          </Text>
        ) : null}
        {notice ? (
          <Text variant="caption" tone="success" accessibilityRole="alert">
            {notice}
          </Text>
        ) : null}
        <Button label={chosenStyle ? `Create video (${chosenStyle.credits} credits)` : 'Create video'} icon="videocam" variant="gold" disabled={!offerId || !style} loading={create.isPending} onPress={submit} />
      </Card>

      <Text variant="heading" accessibilityRole="header">
        Your videos
      </Text>
      {(videos.data ?? []).length === 0 && !videos.isPending ? <EmptyState icon="videocam-outline" title="No videos yet" message="Your first video will appear here while it renders." /> : null}
      {(videos.data ?? []).map((video) => (
        <Pressable key={video.id} accessibilityRole="button" disabled={video.status !== 'completed'} onPress={() => router.push(`/videos/${video.id}`)}>
          <Card style={styles.videoRow}>
            <View style={[styles.thumb, { backgroundColor: theme.cardMuted }]}>
              <Ionicons name={video.status === 'completed' ? 'play' : 'film-outline'} size={22} color={theme.primary} />
            </View>
            <View style={{ flex: 1, gap: 2 }}>
              <Text variant="bodyStrong" tone="heading" numberOfLines={1}>
                {video.offer_name ?? 'Video'}
              </Text>
              <Text variant="caption" tone="muted">
                {videoStyleLabel(video.video_style)} · {relativeTime(video.created_at)}
              </Text>
            </View>
            <StatusBadge status={video.status} />
          </Card>
        </Pressable>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create({
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.sm },
  style: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md, borderWidth: 1.5, borderRadius: Radius.md, padding: Spacing.md },
  photo: { width: '100%', aspectRatio: 1, borderRadius: Radius.md },
  row: { flexDirection: 'row', gap: Spacing.md },
  videoRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md, padding: Spacing.md },
  thumb: { width: 48, height: 64, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
});
