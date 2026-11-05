import { Text as RNText, type TextProps } from 'react-native';

import { Fonts } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

type Variant = 'title' | 'heading' | 'subheading' | 'body' | 'bodyStrong' | 'caption' | 'label';

const sizes: Record<Variant, { fontFamily: string; fontSize: number; lineHeight: number }> = {
  title: { fontFamily: Fonts.displayBold, fontSize: 28, lineHeight: 34 },
  heading: { fontFamily: Fonts.display, fontSize: 20, lineHeight: 26 },
  subheading: { fontFamily: Fonts.display, fontSize: 16, lineHeight: 22 },
  body: { fontFamily: Fonts.body, fontSize: 15, lineHeight: 22 },
  bodyStrong: { fontFamily: Fonts.bodySemiBold, fontSize: 15, lineHeight: 22 },
  caption: { fontFamily: Fonts.body, fontSize: 13, lineHeight: 18 },
  label: { fontFamily: Fonts.bodySemiBold, fontSize: 12, lineHeight: 16 },
};

/**
 * All text in the app. Scales with the phone's text-size setting (dynamic
 * type), capped so layouts don't break at the very largest sizes.
 */
export function Text({ variant = 'body', tone, style, ...props }: TextProps & { variant?: Variant; tone?: 'muted' | 'faint' | 'heading' | 'danger' | 'success' | 'accent' }) {
  const theme = useTheme();
  const color =
    tone === 'muted'
      ? theme.textMuted
      : tone === 'faint'
        ? theme.textFaint
        : tone === 'heading' || variant === 'title' || variant === 'heading' || variant === 'subheading'
          ? theme.heading
          : tone === 'danger'
            ? theme.danger
            : tone === 'success'
              ? theme.success
              : tone === 'accent'
                ? theme.accent
                : theme.text;

  return <RNText maxFontSizeMultiplier={1.8} {...props} style={[sizes[variant], { color }, style]} />;
}
