import * as Clipboard from 'expo-clipboard';
import { File, Paths } from 'expo-file-system';
import { Asset, requestPermissionsAsync } from 'expo-media-library';
import * as Sharing from 'expo-sharing';
import { Platform, Share } from 'react-native';

export async function copyText(text: string): Promise<void> {
  await Clipboard.setStringAsync(text);
}

/** The phone's share sheet for text (WhatsApp, LinkedIn, X, email…). */
export async function shareText(text: string, title?: string): Promise<void> {
  await Share.share({ message: text, title });
}

async function downloadVideo(url: string, id: number): Promise<File> {
  const destination = new File(Paths.cache, `affilstack-video-${id}.mp4`);

  if (destination.exists) return destination;

  return File.downloadFileAsync(url, destination);
}

/**
 * Shares the video file itself through the share sheet, so TikTok,
 * Instagram, YouTube and WhatsApp receive the clip, not a link.
 */
export async function shareVideo(url: string, id: number): Promise<void> {
  if (Platform.OS === 'web' || !(await Sharing.isAvailableAsync())) {
    await Share.share({ message: url });

    return;
  }

  const file = await downloadVideo(url, id);
  await Sharing.shareAsync(file.uri, { mimeType: 'video/mp4', dialogTitle: 'Share your video', UTI: 'public.mpeg-4' });
}

/** Saves the video to the phone's Photos / Gallery. */
export async function saveVideo(url: string, id: number): Promise<boolean> {
  const permission = await requestPermissionsAsync(true);

  if (!permission.granted) return false;

  const file = await downloadVideo(url, id);
  await Asset.create(file.uri);

  return true;
}
