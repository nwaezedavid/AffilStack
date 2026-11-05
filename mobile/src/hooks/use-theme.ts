import { Colors, type ThemeColors } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';

/**
 * The colour set for the phone's light/dark setting (dark mode follows
 * the system, like the rest of the phone).
 */
export function useTheme(): ThemeColors & { scheme: 'light' | 'dark' } {
  const scheme = useColorScheme() === 'dark' ? 'dark' : 'light';

  return { ...Colors[scheme], scheme };
}
