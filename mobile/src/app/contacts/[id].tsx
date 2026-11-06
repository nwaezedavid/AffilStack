import Ionicons from '@expo/vector-icons/Ionicons';
import * as Linking from 'expo-linking';
import { Stack, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Chip, ErrorCard, Field } from '@/components/kit';
import { Screen } from '@/components/screen';
import { Text } from '@/components/text';
import { Radius, Spacing } from '@/constants/theme';
import { useContact, useUpdateContact } from '@/hooks/use-core';
import { useTheme } from '@/hooks/use-theme';
import type { ContactStatus } from '@/lib/types';

const STATUSES: { value: ContactStatus; label: string }[] = [
  { value: 'new', label: 'New' },
  { value: 'contacted', label: 'Contacted' },
  { value: 'qualified', label: 'Qualified' },
  { value: 'customer', label: 'Customer' },
  { value: 'unqualified', label: 'Not a fit' },
];

/** A contact: tap to email, call or WhatsApp; set status, tags and notes. */
export default function ContactScreen() {
  const theme = useTheme();
  const id = Number(useLocalSearchParams<{ id: string }>().id);
  const contact = useContact(id);
  const update = useUpdateContact(id);
  const [notes, setNotes] = useState('');
  const [tagInput, setTagInput] = useState('');
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    if (contact.data) setNotes(contact.data.notes ?? '');
  }, [contact.data]);

  if (contact.isPending) return <ActivityIndicator style={{ marginTop: Spacing.xxl }} color={theme.primary} />;
  if (contact.isError || !contact.data) return <Screen edges={[]}><ErrorCard message={contact.error?.message} onRetry={() => contact.refetch()} /></Screen>;

  const person = contact.data;
  const digits = (person.phone ?? '').replace(/[^\d]/g, '');
  const actions: { icon: keyof typeof Ionicons.glyphMap; label: string; url: string | null }[] = [
    { icon: 'mail-outline', label: 'Email', url: person.email ? `mailto:${person.email}` : null },
    { icon: 'call-outline', label: 'Call', url: person.phone ? `tel:${person.phone}` : null },
    { icon: 'logo-whatsapp', label: 'WhatsApp', url: digits ? `https://wa.me/${digits}` : null },
  ];

  const save = (input: Parameters<typeof update.mutate>[0]) => update.mutate(input, { onSuccess: () => { setSaved(true); setTimeout(() => setSaved(false), 1500); } });

  const addTag = () => {
    const tag = tagInput.trim();
    if (!tag || person.tags.includes(tag)) return setTagInput('');
    save({ tags: [...person.tags, tag] });
    setTagInput('');
  };

  return (
    <Screen edges={['bottom']}>
      <Stack.Screen options={{ title: person.name }} />
      <Text variant="caption" tone="muted">
        {[person.title, person.company, person.location].filter(Boolean).join(' · ')}
      </Text>

      <View style={styles.actions}>
        {actions.map((action) => (
          <Pressable
            key={action.label}
            accessibilityRole="button"
            accessibilityLabel={action.label}
            disabled={!action.url}
            onPress={() => action.url && Linking.openURL(action.url)}
            style={[styles.action, { backgroundColor: theme.card, borderColor: theme.border, opacity: action.url ? 1 : 0.4 }]}>
            <Ionicons name={action.icon} size={22} color={theme.primary} />
            <Text variant="label">{action.label}</Text>
          </Pressable>
        ))}
      </View>

      <Card style={{ gap: Spacing.md }}>
        <Text variant="subheading">Status</Text>
        <View style={styles.wrap}>
          {STATUSES.map((status) => (
            <Chip key={status.value} label={status.label} selected={person.status === status.value} onPress={() => save({ status: status.value })} />
          ))}
        </View>

        <Text variant="subheading">Tags</Text>
        <View style={styles.wrap}>
          {person.tags.map((tag) => (
            <Chip key={tag} label={`${tag} ✕`} onPress={() => save({ tags: person.tags.filter((item) => item !== tag) })} />
          ))}
        </View>
        <View style={styles.tagRow}>
          <View style={{ flex: 1 }}>
            <Field label="Add a tag" value={tagInput} onChangeText={setTagInput} onSubmitEditing={addTag} placeholder="hot lead" returnKeyType="done" />
          </View>
        </View>

        <Field label="Notes" value={notes} onChangeText={setNotes} multiline placeholder="What did you talk about?" />
        <Button label={saved ? 'Saved' : 'Save notes'} icon={saved ? 'checkmark' : 'save-outline'} variant="secondary" loading={update.isPending} onPress={() => save({ notes })} />
      </Card>

      {person.email || person.website ? (
        <Card style={{ gap: 4 }}>
          {person.email ? <Text selectable>{person.email}</Text> : null}
          {person.phone ? <Text selectable>{person.phone}</Text> : null}
          {person.website ? <Text selectable>{person.website}</Text> : null}
        </Card>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  actions: { flexDirection: 'row', gap: Spacing.md },
  action: { flex: 1, alignItems: 'center', gap: 6, paddingVertical: Spacing.md, borderRadius: Radius.md, borderWidth: 1 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.sm },
  tagRow: { flexDirection: 'row', alignItems: 'flex-end', gap: Spacing.sm },
});
