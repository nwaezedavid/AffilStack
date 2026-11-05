import { IBMPlexSans_400Regular, IBMPlexSans_500Medium, IBMPlexSans_600SemiBold } from '@expo-google-fonts/ibm-plex-sans';
import { SpaceGrotesk_600SemiBold, SpaceGrotesk_700Bold, useFonts } from '@expo-google-fonts/space-grotesk';
import { DarkTheme, DefaultTheme, Stack, ThemeProvider } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { Platform } from 'react-native';

import { LockScreen } from '@/components/lock-screen';
import { UpdateRequired } from '@/components/update-required';
import { useAppConfig } from '@/hooks/use-api';
import { useTheme } from '@/hooks/use-theme';
import { APP_VERSION } from '@/lib/config';
import { isBelowMinimum } from '@/lib/version';
import { AuthProvider, useAuth } from '@/providers/auth-provider';
import { QueryProvider } from '@/providers/query-provider';

SplashScreen.preventAutoHideAsync();

function RootNavigator() {
  const theme = useTheme();
  const { status } = useAuth();
  const config = useAppConfig();
  const platform = Platform.OS === 'ios' ? 'ios' : 'android';
  const minimum = config.data?.versions[platform]?.minimum;

  useEffect(() => {
    if (status !== 'loading') SplashScreen.hideAsync();
  }, [status]);

  if (status === 'loading') return null;

  if (Platform.OS !== 'web' && isBelowMinimum(APP_VERSION, minimum)) {
    return <UpdateRequired storeUrl={config.data?.store_urls[platform] ?? null} iconUrl={config.data?.brand.icon_url} />;
  }

  if (status === 'locked') return <LockScreen />;

  const navigationTheme = theme.scheme === 'dark' ? DarkTheme : DefaultTheme;

  return (
    <ThemeProvider value={{ ...navigationTheme, colors: { ...navigationTheme.colors, background: theme.background, card: theme.card, primary: theme.primary, text: theme.heading, border: theme.border } }}>
      <StatusBar style={theme.scheme === 'dark' ? 'light' : 'dark'} />
      <Stack screenOptions={{ headerTintColor: theme.primary, headerTitleStyle: { fontFamily: 'SpaceGrotesk_600SemiBold', color: theme.heading }, contentStyle: { backgroundColor: theme.background } }}>
        <Stack.Protected guard={status === 'signed-in'}>
          <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
          <Stack.Screen name="devices" options={{ title: 'Signed-in devices' }} />
          <Stack.Screen name="delete-account" options={{ title: 'Delete account', presentation: 'modal' }} />
        </Stack.Protected>
        <Stack.Protected guard={status === 'signed-out'}>
          <Stack.Screen name="sign-in" options={{ headerShown: false }} />
        </Stack.Protected>
      </Stack>
    </ThemeProvider>
  );
}

export default function RootLayout() {
  const [fontsLoaded] = useFonts({
    SpaceGrotesk_600SemiBold,
    SpaceGrotesk_700Bold,
    IBMPlexSans_400Regular,
    IBMPlexSans_500Medium,
    IBMPlexSans_600SemiBold,
  });

  if (!fontsLoaded) return null;

  return (
    <QueryProvider>
      <AuthProvider>
        <RootNavigator />
      </AuthProvider>
    </QueryProvider>
  );
}
