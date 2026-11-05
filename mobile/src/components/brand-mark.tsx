import { Image } from 'expo-image';
import { StyleSheet, View } from 'react-native';

import { Text } from '@/components/text';
import { Fonts, Palette } from '@/constants/theme';

/**
 * The AffilStack mark: the logo uploaded in admin (Brand settings) when the
 * server sends one, otherwise the navy-and-gold "AS" monogram the website
 * and Chrome extension use.
 */
/** "AffilStack" → "AS": the capitals of the name, or its first two letters. */
export function brandInitials(name: string): string {
  const capitals = name.replace(/[^A-Z]/g, '');

  return (capitals.length >= 2 ? capitals.slice(0, 2) : name.slice(0, 2)).toUpperCase();
}

export function BrandMark({ size = 56, iconUrl, name = 'AffilStack', onDark = false }: { size?: number; iconUrl?: string | null; name?: string; onDark?: boolean }) {
  if (iconUrl) {
    return <Image source={{ uri: iconUrl }} accessibilityLabel={name} style={{ width: size, height: size, borderRadius: size * 0.22 }} contentFit="contain" />;
  }

  return (
    <View accessibilityRole="image" accessibilityLabel={name} style={[styles.mark, onDark && styles.onDark, { width: size, height: size, borderRadius: size * 0.22 }]}>
      <Text style={{ fontFamily: Fonts.displayBold, fontSize: size * 0.4, lineHeight: size * 0.5, color: Palette.gold400 }}>
        {brandInitials(name)}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  mark: { backgroundColor: Palette.navy900, alignItems: 'center', justifyContent: 'center' },
  onDark: { backgroundColor: Palette.navy800, borderWidth: 1, borderColor: Palette.gold600 },
});
