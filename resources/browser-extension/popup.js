// Popup logic for the AffilStack Research Capture extension.
//
// Deliberately uses activeTab + scripting (injected only when the popup
// opens, only into the current tab) rather than a persistent content
// script with broad host permissions — the extension never runs on a page
// unless the person explicitly opens the popup on it.

const setupEl = document.getElementById('setup');
const formEl = document.getElementById('form');
const pageTitleEl = document.getElementById('pageTitle');
const selectionNoteEl = document.getElementById('selectionNote');
const pageTypeEl = document.getElementById('pageType');
const offerEl = document.getElementById('offer');
const saveButton = document.getElementById('save');
const statusEl = document.getElementById('status');

let currentTab = null;
let selectedText = '';

function setStatus(message, kind) {
  statusEl.textContent = message || '';
  statusEl.className = kind || '';
}

async function getConfig() {
  const { apiBaseUrl, apiToken } = await chrome.storage.local.get(['apiBaseUrl', 'apiToken']);
  return { apiBaseUrl, apiToken };
}

async function apiFetch(path, options = {}) {
  const { apiBaseUrl, apiToken } = await getConfig();
  const response = await fetch(`${apiBaseUrl.replace(/\/+$/, '')}${path}`, {
    ...options,
    headers: {
      Authorization: `Bearer ${apiToken}`,
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(options.headers || {}),
    },
  });

  return response;
}

async function init() {
  const { apiBaseUrl, apiToken } = await getConfig();

  if (!apiBaseUrl || !apiToken) {
    setupEl.hidden = false;
    formEl.hidden = true;

    return;
  }

  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  currentTab = tab;
  pageTitleEl.textContent = tab?.title || tab?.url || '';

  if (tab?.id) {
    try {
      const [{ result }] = await chrome.scripting.executeScript({
        target: { tabId: tab.id },
        func: () => window.getSelection()?.toString() || '',
      });
      selectedText = (result || '').trim();

      if (selectedText) {
        const preview = selectedText.length > 140 ? `${selectedText.slice(0, 140)}…` : selectedText;
        selectionNoteEl.textContent = `Selected text will be included: "${preview}"`;
      } else {
        selectionNoteEl.textContent = 'No text selected — just the page title and URL will be saved.';
      }
    } catch (error) {
      // Some pages (chrome://, the Chrome Web Store, etc.) block script
      // injection entirely — capture still works, just without a selection.
      selectionNoteEl.textContent = 'No text selected — just the page title and URL will be saved.';
    }
  }

  try {
    const response = await apiFetch('/offers');
    if (response.ok) {
      const data = await response.json();
      for (const offer of data.offers || []) {
        const option = document.createElement('option');
        option.value = offer.id;
        option.textContent = offer.product_name;
        offerEl.appendChild(option);
      }
    } else if (response.status === 401) {
      setupEl.hidden = false;
      setupEl.textContent = '';
      const text = document.createTextNode('Your token was rejected — ');
      const link = document.createElement('a');
      link.href = '#';
      link.id = 'openOptions';
      link.textContent = 'reconnect it';
      link.addEventListener('click', openOptions);
      setupEl.appendChild(text);
      setupEl.appendChild(link);
      setupEl.appendChild(document.createTextNode('.'));
      formEl.hidden = true;
    }
  } catch (error) {
    setStatus("Couldn't reach AffilStack — check your connection.", 'error');
  }
}

function openOptions(event) {
  event.preventDefault();
  chrome.runtime.openOptionsPage();
}

document.getElementById('openOptions')?.addEventListener('click', openOptions);

saveButton.addEventListener('click', async () => {
  if (!currentTab?.url) {
    return;
  }

  saveButton.disabled = true;
  setStatus('Saving…');

  try {
    const response = await apiFetch('/clips', {
      method: 'POST',
      body: JSON.stringify({
        source_url: currentTab.url,
        title: currentTab.title || null,
        selected_text: selectedText || null,
        page_type: pageTypeEl.value,
        offer_id: offerEl.value || null,
      }),
    });

    if (response.ok) {
      setStatus('Saved to AffilStack.', 'success');
      setTimeout(() => window.close(), 900);
    } else {
      const body = await response.json().catch(() => ({}));
      setStatus(body.message || 'Could not save this page.', 'error');
      saveButton.disabled = false;
    }
  } catch (error) {
    setStatus("Couldn't reach AffilStack — check your connection.", 'error');
    saveButton.disabled = false;
  }
});

init();
