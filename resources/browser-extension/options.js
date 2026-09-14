// Options page for the AffilStack Research Capture extension.
// Verifies the pasted token against GET /api/me before storing it, so a
// typo or a stale/revoked token is caught here instead of failing silently
// the next time someone tries to save a page.

const apiBaseUrlEl = document.getElementById('apiBaseUrl');
const apiTokenEl = document.getElementById('apiToken');
const statusEl = document.getElementById('status');

function setStatus(message, kind) {
  statusEl.textContent = message || '';
  statusEl.className = kind || '';
}

async function restore() {
  const { apiBaseUrl, apiToken } = await chrome.storage.local.get(['apiBaseUrl', 'apiToken']);
  if (apiBaseUrl) apiBaseUrlEl.value = apiBaseUrl;
  if (apiToken) apiTokenEl.value = apiToken;
}

document.getElementById('save').addEventListener('click', async () => {
  const apiBaseUrl = apiBaseUrlEl.value.trim().replace(/\/+$/, '');
  const apiToken = apiTokenEl.value.trim();

  if (!apiBaseUrl || !apiToken) {
    setStatus('Both fields are required.', 'error');

    return;
  }

  setStatus('Verifying…');

  try {
    const response = await fetch(`${apiBaseUrl}/me`, {
      headers: {
        Authorization: `Bearer ${apiToken}`,
        Accept: 'application/json',
      },
    });

    if (!response.ok) {
      setStatus(
        response.status === 401
          ? 'That token was rejected — check it was copied in full.'
          : `Could not verify (HTTP ${response.status}). Check the API URL.`,
        'error'
      );

      return;
    }

    const data = await response.json();
    await chrome.storage.local.set({ apiBaseUrl, apiToken });
    setStatus(`Connected as ${data.email}.`, 'success');
  } catch (error) {
    setStatus("Couldn't reach that URL — check it's correct and reachable.", 'error');
  }
});

restore();
