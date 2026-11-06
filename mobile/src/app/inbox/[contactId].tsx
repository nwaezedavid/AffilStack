import { Stack, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, KeyboardAvoidingView, Platform, StyleSheet, View } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { ErrorCard, Field } from '@/components/kit';
import { Screen } from '@/components/screen';
import { Text } from '@/components/text';
import { Radius, Spacing } from '@/constants/theme';
import { useConversation, useReplyActions } from '@/hooks/use-core';
import { useTheme } from '@/hooks/use-theme';
import { ApiError } from '@/lib/api';
import { relativeTime } from '@/lib/format';

/**
 * One conversation: the thread, Reply Copilot's suggested answer, "Draft
 * with AI", and Send from the customer's own mailbox.
 */
export default function ConversationScreen() {
  const theme = useTheme();
  const contactId = Number(useLocalSearchParams<{ contactId: string }>().contactId);
  const conversation = useConversation(contactId);
  const actions = useReplyActions(contactId);
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [notice, setNotice] = useState<{ text: string; ok: boolean } | null>(null);

  useEffect(() => {
    if (conversation.data) {
      setSubject((current) => current || conversation.data.reply_subject);
      setBody((current) => current || conversation.data.suggestion?.body || '');
    }
  }, [conversation.data]);

  if (conversation.isPending) return <ActivityIndicator style={{ marginTop: Spacing.xxl }} color={theme.primary} />;
  if (conversation.isError || !conversation.data) return <Screen edges={[]}><ErrorCard message={conversation.error?.message} onRetry={() => conversation.refetch()} /></Screen>;

  const data = conversation.data;
  const fail = (error: Error) => setNotice({ text: error instanceof ApiError ? error.message : 'Something went wrong.', ok: false });

  const draft = () =>
    actions.draft.mutate({ replyId: data.reply_id }, { onSuccess: (result) => { setBody(result.body); setNotice({ text: 'Draft ready — edit anything, then send.', ok: true }); }, onError: fail });

  const send = () =>
    actions.send.mutate({ replyId: data.reply_id, subject, body }, { onSuccess: (result) => { setBody(''); setNotice({ text: result.message, ok: true }); }, onError: fail });

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined} keyboardVerticalOffset={90}>
      <Screen edges={['bottom']} onRefresh={() => conversation.refetch()} refreshing={conversation.isRefetching}>
        <Stack.Screen options={{ title: data.contact.name }} />
        {data.contact.company ? (
          <Text variant="caption" tone="muted">
            {data.contact.company} · {data.contact.email}
          </Text>
        ) : null}

        {data.messages.map((message, index) => (
          <View key={index} style={[styles.bubble, message.direction === 'out' ? { alignSelf: 'flex-end', backgroundColor: theme.infoSoft } : { alignSelf: 'flex-start', backgroundColor: theme.card, borderColor: theme.border, borderWidth: StyleSheet.hairlineWidth }]}>
            <Text variant="label" tone="muted">
              {message.direction === 'out' ? 'You' : data.contact.name} · {relativeTime(message.at)}
            </Text>
            <Text selectable>{message.body}</Text>
          </View>
        ))}

        <Card style={{ gap: Spacing.md }}>
          <Text variant="subheading">Your reply</Text>
          {data.suggestion ? (
            <View style={styles.suggestion}>
              <Text variant="label" tone="accent" style={{ flex: 1 }}>
                ✦ Reply Copilot suggested this answer
              </Text>
              <Button label="Dismiss" variant="ghost" onPress={() => actions.dismiss.mutate(data.reply_id, { onSuccess: () => setBody('') })} />
            </View>
          ) : null}
          <Field label="Subject" value={subject} onChangeText={setSubject} />
          <Field label="Message" value={body} onChangeText={setBody} multiline placeholder="Write your reply…" />
          {notice ? (
            <Text variant="caption" tone={notice.ok ? 'success' : 'danger'} accessibilityRole="alert">
              {notice.text}
            </Text>
          ) : null}
          <View style={styles.row}>
            <View style={{ flex: 1 }}>
              <Button label={`Draft with AI (${data.draft_cost} cr)`} icon="sparkles" variant="secondary" loading={actions.draft.isPending} onPress={draft} />
            </View>
            <View style={{ flex: 1 }}>
              <Button label="Send" icon="send" loading={actions.send.isPending} disabled={!body.trim() || !subject.trim() || !data.email_connected} onPress={send} />
            </View>
          </View>
          {!data.email_connected ? (
            <Text variant="caption" tone="muted">
              Connect your email on the website (Email Sending) to send replies.
            </Text>
          ) : null}
        </Card>
      </Screen>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  bubble: { maxWidth: '88%', padding: Spacing.md, borderRadius: Radius.lg, gap: 4 },
  suggestion: { flexDirection: 'row', alignItems: 'center' },
  row: { flexDirection: 'row', gap: Spacing.md },
});
