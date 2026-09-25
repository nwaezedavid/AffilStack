'use strict';

/**
 * AffilStack API client (API roadmap item #8) — a small, hand-built
 * wrapper around the /v1/* endpoints described in
 * resources/openapi/openapi.yaml, kept in sync with that spec by hand
 * rather than generated, so it stays dependency-free (uses the platform
 * `fetch`, available in Node 18+ and every browser) and easy to read.
 *
 * const { AffilStackClient } = require('affilstack-sdk');
 * const client = new AffilStackClient('aff_xxx');
 * const offer = await client.createOffer({ product_name, product_url, affiliate_network });
 */

class AffilStackApiError extends Error {
  /**
   * @param {string} message
   * @param {number} status
   * @param {unknown} body
   */
  constructor(message, status, body) {
    super(message);
    this.name = 'AffilStackApiError';
    this.status = status;
    this.body = body;
  }
}

class AffilStackClient {
  /**
   * @param {string} apiKey - a token from the dashboard's API Access page ("aff_...")
   * @param {{ baseUrl?: string }} [options]
   */
  constructor(apiKey, options = {}) {
    if (!apiKey) {
      throw new Error('AffilStackClient requires an API key.');
    }

    this.apiKey = apiKey;
    this.baseUrl = (options.baseUrl || 'https://app.affilstack.example/api/v1').replace(/\/+$/, '');
  }

  /**
   * @param {string} method
   * @param {string} path
   * @param {{ query?: Record<string, string|number|undefined>, body?: unknown, idempotencyKey?: string }} [opts]
   */
  async _request(method, path, opts = {}) {
    const url = new URL(this.baseUrl + path);
    for (const [key, value] of Object.entries(opts.query || {})) {
      if (value !== undefined && value !== null) {
        url.searchParams.set(key, String(value));
      }
    }

    const headers = {
      Authorization: `Bearer ${this.apiKey}`,
      Accept: 'application/json',
    };
    if (opts.body !== undefined) {
      headers['Content-Type'] = 'application/json';
    }
    if (opts.idempotencyKey) {
      headers['Idempotency-Key'] = opts.idempotencyKey;
    }

    const response = await fetch(url, {
      method,
      headers,
      body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
    });

    const text = await response.text();
    const data = text ? JSON.parse(text) : null;

    if (!response.ok) {
      throw new AffilStackApiError(
        (data && data.message) || `AffilStack API request failed with status ${response.status}`,
        response.status,
        data
      );
    }

    return data;
  }

  // --- Account -------------------------------------------------------

  me() {
    return this._request('GET', '/me');
  }

  // --- Offers ----------------------------------------------------------

  listOffers({ perPage } = {}) {
    return this._request('GET', '/offers', { query: { per_page: perPage } });
  }

  getOffer(offerId) {
    return this._request('GET', `/offers/${offerId}`);
  }

  /**
   * @param {{ product_name: string, product_url: string, affiliate_network: string, affiliate_link?: string }} offer
   * @param {{ idempotencyKey?: string }} [opts]
   */
  createOffer(offer, opts = {}) {
    return this._request('POST', '/offers', { body: offer, idempotencyKey: opts.idempotencyKey });
  }

  // --- Generations -------------------------------------------------------

  listGenerations({ offerId, module, perPage } = {}) {
    return this._request('GET', '/generations', {
      query: { offer_id: offerId, module, per_page: perPage },
    });
  }

  getGeneration(generationId) {
    return this._request('GET', `/generations/${generationId}`);
  }

  // --- CRM contacts --------------------------------------------------------

  listCrmContacts({ perPage } = {}) {
    return this._request('GET', '/crm-contacts', { query: { per_page: perPage } });
  }

  getCrmContact(contactId) {
    return this._request('GET', `/crm-contacts/${contactId}`);
  }

  createCrmContact(contact, opts = {}) {
    return this._request('POST', '/crm-contacts', { body: contact, idempotencyKey: opts.idempotencyKey });
  }

  /**
   * @param {Array<Record<string, unknown>>} contacts - up to 100
   * @returns {Promise<{ created: object[], failed: { index: number, errors: object }[] }>}
   */
  bulkCreateCrmContacts(contacts) {
    return this._request('POST', '/crm-contacts/bulk', { body: { contacts } });
  }

  updateCrmContact(contactId, changes) {
    return this._request('PATCH', `/crm-contacts/${contactId}`, { body: changes });
  }

  // --- Referrals ---------------------------------------------------------

  getReferralsSummary() {
    return this._request('GET', '/referrals/summary');
  }
}

module.exports = { AffilStackClient, AffilStackApiError };
