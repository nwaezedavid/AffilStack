import { router } from 'expo-router';
import { useState } from 'react';

import { Button } from '@/components/button';
import { Field } from '@/components/kit';
import { Screen } from '@/components/screen';
import { Text } from '@/components/text';
import { useCreateOffer } from '@/hooks/use-core';
import { ApiError } from '@/lib/api';

/** Add a product: AffilStack researches it (uses research credits). */
export default function NewProductScreen() {
  const create = useCreateOffer();
  const [name, setName] = useState('');
  const [url, setUrl] = useState('');
  const [network, setNetwork] = useState('');
  const [link, setLink] = useState('');

  const error = create.error instanceof ApiError ? Object.values(create.error.errors)[0]?.[0] ?? create.error.message : create.error?.message;

  const submit = () =>
    create.mutate(
      { product_name: name.trim(), product_url: url.trim(), affiliate_network: network.trim(), affiliate_link: link.trim() || undefined },
      { onSuccess: (offer) => router.replace(`/products/${offer.id}`) },
    );

  return (
    <Screen edges={['bottom']}>
      <Text tone="muted">Paste the product's page. AffilStack researches who buys it, where to find them and the best angle — usually in a couple of minutes.</Text>
      <Field label="Product name" value={name} onChangeText={setName} placeholder="Notion AI" autoCapitalize="words" />
      <Field label="Product page" value={url} onChangeText={setUrl} placeholder="https://www.notion.so/product/ai" keyboardType="url" autoCapitalize="none" autoCorrect={false} />
      <Field label="Affiliate network" value={network} onChangeText={setNetwork} placeholder="Impact, PartnerStack, Direct…" />
      <Field label="Your affiliate link (optional)" value={link} onChangeText={setLink} placeholder="https://…" keyboardType="url" autoCapitalize="none" autoCorrect={false} />
      {error ? (
        <Text variant="caption" tone="danger" accessibilityRole="alert">
          {error}
        </Text>
      ) : null}
      <Button label="Research this product" icon="sparkles" variant="gold" loading={create.isPending} disabled={!name.trim() || !url.trim() || !network.trim()} onPress={submit} />
    </Screen>
  );
}
