import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { StyleSheet, Switch, View } from 'react-native';

import { Card } from '@/components/card';
import { ListRow } from '@/components/list-row';
import { Screen } from '@/components/screen';
import { Text } from '@/components/text';
import { Palette, Spacing } from '@/constants/theme';
import { useAppConfig, useHome } from '@/hooks/use-api';
import { useTheme } from '@/hooks/use-theme';
import { APP_VERSION } from '@/lib/config';
import { confirm } from '@/lib/confirm';
import { useAuth } from '@/providers/auth-provider';

/**
 * Account, security and sign-out. Billing is shown as status only: plans,
 * credits and top-ups are managed on the website in version 1.
 */
export default function MoreScreen() {
  const theme = useTheme();
  const home = useHome();
  const config = useAppConfig();
  const { signOut, signOutEverywhere, biometricAvailable, biometricEnabled, setBiometricEnabled } = useAuth();
  const account = home.data?.account;

  const askSignOut = async () => {
    if (await confirm('Sign out?', 'You can sign in again at any time.', 'Sign out')) await signOut();
  };

  const askSignOutEverywhere = async () => {
    if (await confirm('Sign out everywhere?', 'Every phone signed in to your AffilStack account will be signed out, including this one.', 'Sign out everywhere', true)) {
      await signOutEverywhere();
    }
  };

  return (
    <Screen>
      <Text variant="title" accessibilityRole="header">
        More
      </Text>

      {account ? (
        <Card style={styles.profile}>
          <View style={[styles.avatar, { backgroundColor: Palette.navy900 }]}>
            <Text variant="subheading" style={{ color: Palette.gold400 }}>
              {account.initials}
            </Text>
          </View>
          <View style={{ flex: 1 }}>
            <Text variant="subheading">{account.name}</Text>
            <Text variant="caption" tone="muted">
              {account.email}
            </Text>
            {account.is_seat ? (
              <Text variant="label" tone="accent">
                Team seat{account.seat_role ? ` · ${account.seat_role}` : ''}
              </Text>
            ) : null}
          </View>
        </Card>
      ) : null}

      <Card>
        <Text variant="label" tone="faint" style={styles.section}>
          FIND & CLOSE BUYERS
        </Text>
        <ListRow icon="mail-unread-outline" label="Inbox & replies" detail="Answer contacts who wrote back" onPress={() => router.push('/inbox')} />
        <ListRow icon="people-outline" label="Contacts" detail="Search, tag and call your leads" onPress={() => router.push('/contacts')} />
      </Card>

      <Card>
        <Text variant="label" tone="faint" style={styles.section}>
          SECURITY
        </Text>
        {biometricAvailable ? (
          <ListRow
            icon="finger-print"
            label="Unlock with Face ID / fingerprint"
            detail="Asks when you open the app"
            right={<Switch value={biometricEnabled} onValueChange={(value) => void setBiometricEnabled(value)} trackColor={{ true: theme.primary }} accessibilityLabel="Unlock with Face ID or fingerprint" />}
          />
        ) : null}
        <ListRow
          icon="shield-checkmark-outline"
          label="Two-factor sign-in"
          detail={account?.two_factor_enabled ? 'On' : 'Off — turn it on in your profile on the website'}
        />
        <ListRow icon="phone-portrait-outline" label="Signed-in devices" onPress={() => router.push('/devices')} />
        <ListRow icon="log-out-outline" label="Sign out everywhere" onPress={askSignOutEverywhere} />
      </Card>

      <Card>
        <Text variant="label" tone="faint" style={styles.section}>
          ABOUT
        </Text>
        {config.data ? (
          <>
            <ListRow icon="document-text-outline" label="Privacy policy" onPress={() => WebBrowser.openBrowserAsync(config.data.links.privacy)} />
            <ListRow icon="reader-outline" label="Terms of service" onPress={() => WebBrowser.openBrowserAsync(config.data.links.terms)} />
          </>
        ) : null}
        <ListRow icon="information-circle-outline" label="App version" detail={APP_VERSION} />
      </Card>

      <Card>
        <ListRow icon="exit-outline" label="Sign out" onPress={askSignOut} />
        <ListRow icon="trash-outline" label="Delete my account" danger onPress={() => router.push('/delete-account')} />
      </Card>
    </Screen>
  );
}

const styles = StyleSheet.create({
  profile: { flexDirection: 'row', alignItems: 'center', gap: Spacing.md },
  avatar: { width: 52, height: 52, borderRadius: 26, alignItems: 'center', justifyContent: 'center' },
  section: { letterSpacing: 0.8, marginBottom: Spacing.xs },
});
