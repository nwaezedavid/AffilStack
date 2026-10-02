// Small client for the AffilStack extension API, shared by the popup and the
// options page. Every call is a plain bearer-token request to the user's own
// AffilStack site (config.js / the API URL saved in options).

const AffilStackApi = (() => {
  async function config() {
    const stored = await chrome.storage.local.get(['apiBaseUrl', 'apiToken']);
    const apiBaseUrl = String(stored.apiBaseUrl || AFFILSTACK_DEFAULT_API_BASE_URL).replace(/\/+$/, '');

    return { apiBaseUrl, apiToken: stored.apiToken || '' };
  }

  /** The AffilStack site itself ("https://affilstack.com/api" → "https://affilstack.com"). */
  function siteUrl(apiBaseUrl) {
    return String(apiBaseUrl || AFFILSTACK_DEFAULT_API_BASE_URL).replace(/\/+$/, '').replace(/\/api$/, '');
  }

  /**
   * @returns {Promise<{ok: boolean, status: number, data: any, networkError?: boolean}>}
   */
  async function request(path, { method = 'GET', body, apiBaseUrl, apiToken } = {}) {
    const settings = apiBaseUrl && apiToken !== undefined ? { apiBaseUrl, apiToken } : await config();

    try {
      const response = await fetch(`${settings.apiBaseUrl}${path}`, {
        method,
        headers: {
          Authorization: `Bearer ${settings.apiToken}`,
          Accept: 'application/json',
          ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
      });
      const data = await response.json().catch(() => ({}));

      return { ok: response.ok, status: response.status, data };
    } catch (error) {
      return { ok: false, status: 0, data: {}, networkError: true };
    }
  }

  /** A friendly message for a failed request. */
  function errorMessage(result, fallback = 'Something went wrong — please try again.') {
    if (result.networkError) {
      return "Couldn't reach AffilStack — check your internet connection.";
    }
    if (result.status === 401) {
      return 'Your token was rejected — reconnect the extension in its settings.';
    }
    if (result.status === 429) {
      return "You're going a little fast — wait a minute and try again.";
    }
    const data = result.data || {};
    if (data.errors && typeof data.errors === 'object') {
      const first = Object.values(data.errors)[0];
      if (Array.isArray(first) && first[0]) {
        return first[0];
      }
    }

    return data.message || fallback;
  }

  return { config, siteUrl, request, errorMessage };
})();
