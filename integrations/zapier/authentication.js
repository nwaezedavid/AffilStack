// API roadmap item #9 — custom API-key auth. The user pastes a token
// minted from the AffilStack dashboard's API Access page; testAuth calls
// GET /me (see MeController), which every valid AffilStack token can
// reach regardless of scope/sandbox mode.
const testAuth = (z, bundle) =>
  z.request({ url: `${bundle.authData.apiUrl}/me` }).then((response) => response.data);

module.exports = {
  type: 'custom',
  test: testAuth,
  fields: [
    {
      key: 'apiUrl',
      label: 'AffilStack API URL',
      type: 'string',
      required: true,
      default: 'https://app.affilstack.example/api/v1',
      helpText: 'Your AffilStack API base URL — usually `https://<your-domain>/api/v1`.',
    },
    {
      key: 'apiKey',
      label: 'API Token',
      type: 'password',
      required: true,
      helpText: 'From AffilStack: **Dashboard → API Access → Generate token**. A read-only or sandbox token both work here, with their usual limits.',
    },
  ],
  connectionLabel: '{{bundle.inputData.name}} ({{bundle.inputData.email}})',
};
