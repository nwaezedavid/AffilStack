import { ComingSoon } from '@/components/coming-soon';

export default function VideosScreen() {
  return (
    <ComingSoon
      title="One-click videos"
      icon="videocam-outline"
      description="Turn a product photo into a short video."
      bullets={['Pick one of the three styles and press Create', 'Use a photo from your camera or gallery', 'Share to TikTok, Instagram, YouTube or WhatsApp']}
    />
  );
}
