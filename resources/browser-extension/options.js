// Options page: stores the API token after verifying it against GET /api/me,
// so a typo or a revoked token is caught here instead of failing silently
// the next time someone uses the popup.

const apiBaseUrlEl = document.getElementById('apiBaseUrl');
const apiTokenEl = document.getElementById('apiToken');
const statusEl = document.getElementById('status');
const statusTextEl = document.getElementById('statusText');
const disconnectEl = document.getElementById('disconnect');

function setStatus(kind, message, link) {
  statusEl.hidden = !message;
  statusEl.className = `status status-${kind}`;
  statusTextEl.textContent = message || '';
  if (link) {
    statusTextEl.appendChild(document.createTextNode(' '));
    const a = document.createElement('a');
    a.href = link.url;
    a.target = '_blank';
    a.rel = 'noopener';
    a.textContent = link.text;
    statusTextEl.appendChild(a);
  }
}

function currentBase() {
  return (apiBaseUrlEl.value.trim() || AFFILSTACK_DEFAULT_API_BASE_URL).replace(/\/+$/, '');
}

function extensionPageUrl() {
  return `${AffilStackApi.siteUrl(currentBase())}/extension`;
}

async function verify(apiBaseUrl, apiToken, { save }) {
  setStatus('info', 'Checking your token…');
  const result = await AffilStackApi.request('/me', { apiBaseUrl, apiToken });

  if (result.ok) {
    if (save) {
      await chrome.storage.local.set({ apiBaseUrl, apiToken });
    }
    disconnectEl.hidden = false;
    setStatus('ok', `Connected as ${result.data.email}. Open Google Maps or LinkedIn and click the AffilStack icon.`);
    return;
  }

  if (result.status === 401) {
    setStatus('error', 'That token was rejected — it may be revoked or copied incompletely.', { url: extensionPageUrl(), text: 'Generate a new token' });
  } else if (result.networkError) {
    setStatus('error', "Couldn't reach that address — check your connection and the API URL under Advanced.");
  } else {
    setStatus('error', `Couldn't verify the token (HTTP ${result.status}). Check the API URL under Advanced.`);
  }
}

document.getElementById('save').addEventListener('click', async () => {
  const apiToken = apiTokenEl.value.trim();
  if (!apiToken) {
    setStatus('error', 'Paste your API token first.', { url: extensionPageUrl(), text: 'Get a token' });
    apiTokenEl.focus();
    return;
  }
  await verify(currentBase(), apiToken, { save: true });
});

disconnectEl.addEventListener('click', async () => {
  await chrome.storage.local.remove(['apiToken']);
  apiTokenEl.value = '';
  disconnectEl.hidden = true;
  setStatus('info', 'Disconnected. Paste a token to connect again.');
});

document.getElementById('dashboardLink').addEventListener('click', (event) => {
  event.preventDefault();
  chrome.tabs.create({ url: extensionPageUrl() });
});

(async () => {
  const { apiBaseUrl, apiToken } = await chrome.storage.local.get(['apiBaseUrl', 'apiToken']);
  apiBaseUrlEl.value = apiBaseUrl || AFFILSTACK_DEFAULT_API_BASE_URL;
  if (apiToken) {
    apiTokenEl.value = apiToken;
    await verify(currentBase(), apiToken, { save: false });
  }
})();
