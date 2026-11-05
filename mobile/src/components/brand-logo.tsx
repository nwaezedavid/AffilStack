import { Image } from 'expo-image';

const RATIO = { white: 1202 / 653, color: 451 / 257 } as const;

/**
 * The full AffilStack logo (symbol + wordmark): the white version on dark
 * backgrounds, the blue version on light ones. From the owner's logo files.
 */
export function BrandLogo({ width = 180, variant = 'white' }: { width?: number; variant?: 'white' | 'color' }) {
  return (
    <Image
      source={variant === 'white' ? require('@/assets/images/logo-white.png') : require('@/assets/images/logo-color.png')}
      accessibilityLabel="AffilStack"
      style={{ width, height: width / RATIO[variant] }}
      contentFit="contain"
    />
  );
}
