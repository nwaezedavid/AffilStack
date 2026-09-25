// API roadmap item #9 — a REST Hook trigger. Subscribing registers a
// webhook_endpoints row (see ZapierSubscriptionsController::store()) for
// AffilStack's existing "generation.completed" event (config('webhooks.events'))
// — there's no separate "offer ready" event on the backend, since an
// offer's own research result IS just its "research"-module Generation
// completing (see app/Models/Generation.php's booted() hook). perform()
// filters the caught payload down to that one module so this trigger only
// fires for a finished offer, not every other content type.
const subscribeHook = (z, bundle) =>
  z
    .request({
      url: `${bundle.authData.apiUrl}/zapier/subscriptions`,
      method: 'POST',
      body: { target_url: bundle.targetUrl, event: 'generation.completed' },
    })
    .then((response) => response.data);

const unsubscribeHook = (z, bundle) =>
  z
    .request({
      url: `${bundle.authData.apiUrl}/zapier/subscriptions/${bundle.subscribeData.id}`,
      method: 'DELETE',
    })
    .then((response) => response.data);

// Zapier calls perform() with the caught delivery body — see
// SendWebhookDelivery::handle(): { event, data, delivery_id, timestamp }.
const perform = (z, bundle) => {
  const event = bundle.cleanedRequest || {};
  const generation = event.data || {};

  if (event.event !== 'generation.completed' || generation.module !== 'research') {
    return [];
  }

  return [{ id: event.delivery_id, ...generation, completed_at: generation.completed_at || event.timestamp }];
};

// Lets "Test trigger" in the Zap editor show real sample data without
// needing a live webhook delivery yet.
const performList = (z, bundle) =>
  z
    .request({ url: `${bundle.authData.apiUrl}/generations`, params: { per_page: 3, module: 'research' } })
    .then((response) => (response.data && response.data.data) || []);

module.exports = {
  key: 'new_offer_research_completed',
  noun: 'Offer',
  display: {
    label: 'New Offer Research Completed',
    description: 'Triggers when an offer’s research finishes.',
  },
  operation: {
    type: 'hook',
    performSubscribe: subscribeHook,
    performUnsubscribe: unsubscribeHook,
    perform,
    performList,
    sample: {
      id: 101,
      generation_id: 501,
      offer_id: 55,
      module: 'research',
      completed_at: '2026-09-25T12:00:00+00:00',
    },
  },
};
