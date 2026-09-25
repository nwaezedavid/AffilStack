// API roadmap item #9 — wraps POST /offers (OffersController::store()).
// Metered on a live token ($0.75/call — see config('api_billing.costs'));
// free and instant on a sandbox token (see the auth field's helpText).
const perform = (z, bundle) =>
  z
    .request({
      url: `${bundle.authData.apiUrl}/offers`,
      method: 'POST',
      body: {
        product_name: bundle.inputData.product_name,
        product_url: bundle.inputData.product_url,
        affiliate_network: bundle.inputData.affiliate_network,
        affiliate_link: bundle.inputData.affiliate_link,
      },
      // Zapier retries a create automatically on a timeout — an
      // Idempotency-Key (API roadmap item #1) keyed to the Zap's own run
      // makes that retry safe instead of risking a second offer.
      headers: bundle.meta.zap && bundle.meta.zap.id
        ? { 'Idempotency-Key': `zapier-${bundle.meta.zap.id}-${bundle.meta.zap.reload ? 'reload' : bundle.meta.limit || 'run'}` }
        : {},
    })
    .then((response) => response.data);

module.exports = {
  key: 'create_offer',
  noun: 'Offer',
  display: {
    label: 'Create Offer',
    description: 'Runs offer research for a new product/service.',
  },
  operation: {
    inputFields: [
      { key: 'product_name', label: 'Product Name', type: 'string', required: true },
      { key: 'product_url', label: 'Product URL', type: 'string', required: true },
      { key: 'affiliate_network', label: 'Affiliate Network', type: 'string', required: true, helpText: 'e.g. ShareASale, Impact, PartnerStack.' },
      { key: 'affiliate_link', label: 'Affiliate Link', type: 'string', required: false },
    ],
    perform,
    sample: {
      id: 55,
      product_name: 'Acme Widget',
      product_url: 'https://acme.example/widget',
      affiliate_network: 'ShareASale',
      status: 'queued',
    },
  },
};
