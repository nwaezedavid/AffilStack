// API roadmap item #9 — REST Hook trigger for the "crm_contact.created"
// event (config('webhooks.events')), fired whenever a contact is created
// from any of AffilStack's three entry points (API, Lead Finder, or the
// dashboard's own CRM form) — see App\Models\CrmContact::booted().
const subscribeHook = (z, bundle) =>
  z
    .request({
      url: `${bundle.authData.apiUrl}/zapier/subscriptions`,
      method: 'POST',
      body: { target_url: bundle.targetUrl, event: 'crm_contact.created' },
    })
    .then((response) => response.data);

const unsubscribeHook = (z, bundle) =>
  z
    .request({
      url: `${bundle.authData.apiUrl}/zapier/subscriptions/${bundle.subscribeData.id}`,
      method: 'DELETE',
    })
    .then((response) => response.data);

const perform = (z, bundle) => {
  const event = bundle.cleanedRequest || {};
  const contact = event.data || {};

  return [{ id: event.delivery_id, ...contact }];
};

const performList = (z, bundle) =>
  z
    .request({ url: `${bundle.authData.apiUrl}/crm-contacts`, params: { per_page: 3 } })
    .then((response) => (response.data && response.data.data) || []);

module.exports = {
  key: 'new_crm_contact',
  noun: 'CRM Contact',
  display: {
    label: 'New CRM Contact',
    description: 'Triggers when a new CRM contact is created in AffilStack.',
  },
  operation: {
    type: 'hook',
    performSubscribe: subscribeHook,
    performUnsubscribe: unsubscribeHook,
    perform,
    performList,
    sample: {
      id: 202,
      contact_id: 77,
      name: 'Jane Doe',
      email: 'jane@example.com',
      company: 'Acme Inc.',
      source: 'api',
      status: 'new',
    },
  },
};
