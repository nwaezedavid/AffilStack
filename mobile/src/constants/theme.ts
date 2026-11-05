import { Platform } from 'react-native';

/**
 * AffilStack brand palette — the same tokens as app.affilstack.com's
 * Tailwind theme (resources/css/app.css): navy, brand blue, gold, ink and
 * rose, so the app and the dashboard look like one product.
 */
export const Palette = {
  navy950: '#0a1730',
  navy900: '#0b1e3d',
  navy800: '#142752',
  navy700: '#1d3568',
  brand50: '#eef2ff',
  brand100: '#dbe4ff',
  brand500: '#2452d9',
  brand600: '#1b3fb3',
  brand700: '#16328f',
  gold300: '#e0b563',
  gold400: '#d3a854',
  gold500: '#c9a24a',
  gold600: '#9c7a2e',
  ink900: '#101828',
  ink600: '#48506b',
  ink400: '#8891ab',
  surface: '#ffffff',
  surfaceMuted: '#f5f6fa',
  line: '#dfe3ee',
  rose50: '#fff1f2',
  rose600: '#e11d48',
  amber50: '#fffbeb',
  amber800: '#92400e',
  emerald50: '#ecfdf5',
  emerald600: '#059669',
} as const;

export type ThemeColors = {
  background: string;
  card: string;
  cardMuted: string;
  border: string;
  text: string;
  textMuted: string;
  textFaint: string;
  primary: string;
  primaryText: string;
  accent: string;
  heading: string;
  danger: string;
  dangerSoft: string;
  success: string;
  successSoft: string;
  warningSoft: string;
  warningText: string;
  infoSoft: string;
  infoText: string;
  tabBar: string;
};

export const Colors: Record<'light' | 'dark', ThemeColors> = {
  light: {
    background: Palette.surfaceMuted,
    card: Palette.surface,
    cardMuted: Palette.surfaceMuted,
    border: Palette.line,
    text: Palette.ink900,
    textMuted: Palette.ink600,
    textFaint: Palette.ink400,
    primary: Palette.brand500,
    primaryText: '#ffffff',
    accent: Palette.gold400,
    heading: Palette.navy900,
    danger: Palette.rose600,
    dangerSoft: Palette.rose50,
    success: Palette.emerald600,
    successSoft: Palette.emerald50,
    warningSoft: Palette.amber50,
    warningText: Palette.amber800,
    infoSoft: Palette.brand50,
    infoText: Palette.brand700,
    tabBar: Palette.surface,
  },
  dark: {
    background: Palette.navy950,
    card: Palette.navy900,
    cardMuted: Palette.navy800,
    border: Palette.navy700,
    text: '#f5f6fa',
    textMuted: '#c3c9da',
    textFaint: Palette.ink400,
    primary: '#4f78f0',
    primaryText: '#ffffff',
    accent: Palette.gold300,
    heading: '#ffffff',
    danger: '#fb7185',
    dangerSoft: '#3b1220',
    success: '#34d399',
    successSoft: '#0b3326',
    warningSoft: '#3a2a0b',
    warningText: '#fcd34d',
    infoSoft: Palette.navy800,
    infoText: '#a8bdfc',
    tabBar: Palette.navy900,
  },
};

/**
 * Space Grotesk for headings, IBM Plex Sans for body text — the
 * dashboard's two fonts, loaded in the root layout.
 */
export const Fonts = {
  display: 'SpaceGrotesk_600SemiBold',
  displayBold: 'SpaceGrotesk_700Bold',
  body: 'IBMPlexSans_400Regular',
  bodyMedium: 'IBMPlexSans_500Medium',
  bodySemiBold: 'IBMPlexSans_600SemiBold',
  mono: Platform.select({ ios: 'Menlo', android: 'monospace', default: 'monospace' }),
} as const;

export const Spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  xxl: 32,
} as const;

export const Radius = {
  sm: 8,
  md: 12,
  lg: 16,
  pill: 999,
} as const;

export const MaxContentWidth = 720;
