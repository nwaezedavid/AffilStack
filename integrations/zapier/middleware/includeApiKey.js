// Runs before every z.request() call (see index.js's beforeRequest) so no
// individual trigger/create needs to remember to attach the bearer token
// itself — mirrors how every other AffilStack SDK/client authenticates.
const includeApiKey = (request, z, bundle) => {
  if (bundle.authData && bundle.authData.apiKey) {
    request.headers = request.headers || {};
    request.headers.Authorization = `Bearer ${bundle.authData.apiKey}`;
  }

  return request;
};

module.exports = includeApiKey;
