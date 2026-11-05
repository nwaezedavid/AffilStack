import { useState } from 'react';
import { StyleSheet, TextInput, View } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Screen } from '@/components/screen';
import { Text } from '@/components/text';
import { Fonts, Radius, Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { ApiError, api } from '@/lib/api';
import { confirm } from '@/lib/confirm';
import { useAuth } from '@/providers/auth-provider';

/**
 * Delete the account from inside the app (an Apple requirement). Same as
 * the website: the account is deactivated and support can restore it for
 * 30 days; the subscription stops. Google accounts without a password can
 * sign in again and type DELETE instead.
 */
export default function DeleteAccountScreen() {
  const theme = useTheme();
  const { forgetDevice } = useAuth();
  const [password, setPassword] = useState('');
  const [typed, setTyped] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async () => {
    if (!(await confirm('Delete your account?', 'Your subscription stops and you will be signed out everywhere. Support can restore the account within 30 days.', 'Delete account', true))) {
      return;
    }

    setBusy(true);
    setError(null);

    try {
      await api('/v1/mobile/account', { method: 'DELETE', body: password ? { password } : { confirm: typed.trim().toUpperCase() } });
      await forgetDevice();
    } catch (exception) {
      setError(exception instanceof ApiError ? (exception.errors.password?.[0] ?? exception.message) : 'Something went wrong. Please try again.');
    } finally {
      setBusy(false);
    }
  };

  const inputStyle = [styles.input, { borderColor: theme.border, color: theme.text, backgroundColor: theme.card }];

  return (
    <Screen edges={['bottom']}>
      <Card style={{ gap: Spacing.md }}>
        <Text variant="subheading">This deletes your AffilStack account</Text>
        <Text tone="muted">Your plan stops, your phones and API keys are signed out, and team seats you own are paused. Contact support within 30 days if you change your mind.</Text>
      </Card>

      <View style={{ gap: Spacing.sm }}>
        <Text variant="bodyStrong" tone="heading">
          Your password
        </Text>
        <TextInput value={password} onChangeText={setPassword} secureTextEntry autoComplete="current-password" placeholder="Password" placeholderTextColor={theme.textFaint} style={inputStyle} accessibilityLabel="Your password" />
        <Text variant="caption" tone="muted">
          Signed up with Google and never set a password? Sign out, sign in again, then type DELETE below within 15 minutes.
        </Text>
        <TextInput value={typed} onChangeText={setTyped} autoCapitalize="characters" placeholder="Type DELETE" placeholderTextColor={theme.textFaint} style={inputStyle} accessibilityLabel="Type DELETE to confirm" />
      </View>

      {error ? (
        <Text variant="caption" tone="danger" accessibilityRole="alert">
          {error}
        </Text>
      ) : null}

      <Button label="Delete my account" variant="danger" icon="trash-outline" loading={busy} disabled={!password && typed.trim().toUpperCase() !== 'DELETE'} onPress={submit} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  input: { borderWidth: 1, borderRadius: Radius.md, paddingHorizontal: Spacing.md, minHeight: 48, fontFamily: Fonts.body, fontSize: 15 },
});
