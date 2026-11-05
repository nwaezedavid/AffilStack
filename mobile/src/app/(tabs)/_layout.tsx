import Ionicons from '@expo/vector-icons/Ionicons';
import { Tabs } from 'expo-router';
import { Platform, type ColorValue } from 'react-native';

import { Fonts } from '@/constants/theme';
import { useHome } from '@/hooks/use-api';
import { useTheme } from '@/hooks/use-theme';

type IconName = keyof typeof Ionicons.glyphMap;

function icon(name: IconName, focusedName: IconName) {
  return ({ color, focused, size }: { color: ColorValue; focused: boolean; size: number }) => <Ionicons name={focused ? focusedName : name} size={size} color={color as string} />;
}

/** The five main sections, matching the dashboard's navigation groups. */
export default function TabLayout() {
  const theme = useTheme();
  const home = useHome();
  const approvals = home.data?.agents.pending_approvals ?? 0;

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: theme.scheme === 'dark' ? theme.accent : theme.primary,
        tabBarInactiveTintColor: theme.textFaint,
        tabBarStyle: { backgroundColor: theme.tabBar, borderTopColor: theme.border, ...(Platform.OS === 'web' ? { height: 64, paddingBottom: 8 } : {}) },
        tabBarLabelStyle: { fontFamily: Fonts.bodyMedium, fontSize: 11 },
      }}>
      <Tabs.Screen name="index" options={{ title: 'Home', tabBarIcon: icon('home-outline', 'home') }} />
      <Tabs.Screen name="products" options={{ title: 'Products', tabBarIcon: icon('cube-outline', 'cube') }} />
      <Tabs.Screen name="videos" options={{ title: 'Videos', tabBarIcon: icon('videocam-outline', 'videocam') }} />
      <Tabs.Screen
        name="agents"
        options={{ title: 'Agents', tabBarIcon: icon('sparkles-outline', 'sparkles'), tabBarBadge: approvals > 0 ? approvals : undefined }}
      />
      <Tabs.Screen name="more" options={{ title: 'More', tabBarIcon: icon('menu-outline', 'menu') }} />
    </Tabs>
  );
}
