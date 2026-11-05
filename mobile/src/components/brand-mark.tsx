import { Image } from 'expo-image';

/**
 * The AffilStack app mark: the logo uploaded in admin (Brand settings) when
 * the server sends one, otherwise the bundled app icon (the AffilStack
 * symbol on the navy-to-blue brand gradient).
 */
export function BrandMark({ size = 56, iconUrl, name = 'AffilStack' }: { size?: number; iconUrl?: string | null; name?: string }) {
  return (
    <Image
      source={iconUrl ? { uri: iconUrl } : require('@/assets/images/icon.png')}
      accessibilityLabel={name}
      style={{ width: size, height: size, borderRadius: size * 0.22 }}
      contentFit="cover"
    />
  );
}
