// API roadmap item #9 — wraps POST /crm-contacts (CrmContactsController::store()). Free.
const perform = (z, bundle) =>
  z
    .request({
      url: `${bundle.authData.apiUrl}/crm-contacts`,
      method: 'POST',
      body: {
        name: bundle.inputData.name,
        company: bundle.inputData.company,
        title: bundle.inputData.title,
        email: bundle.inputData.email,
        phone: bundle.inputData.phone,
        website: bundle.inputData.website,
        location: bundle.inputData.location,
        notes: bundle.inputData.notes,
      },
    })
    .then((response) => response.data);

module.exports = {
  key: 'create_crm_contact',
  noun: 'CRM Contact',
  display: {
    label: 'Create CRM Contact',
    description: 'Adds a new contact to AffilStack’s CRM.',
  },
  operation: {
    inputFields: [
      { key: 'name', label: 'Name', type: 'string' },
      { key: 'company', label: 'Company', type: 'string' },
      { key: 'title', label: 'Title', type: 'string' },
      { key: 'email', label: 'Email', type: 'string' },
      { key: 'phone', label: 'Phone', type: 'string' },
      { key: 'website', label: 'Website', type: 'string' },
      { key: 'location', label: 'Location', type: 'string' },
      { key: 'notes', label: 'Notes', type: 'text' },
    ],
    perform,
    sample: {
      id: 77,
      name: 'Jane Doe',
      email: 'jane@example.com',
      company: 'Acme Inc.',
      source: 'api',
      status: 'new',
    },
  },
};
