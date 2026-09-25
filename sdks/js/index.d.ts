export class AffilStackApiError extends Error {
  status: number;
  body: unknown;
}

export interface AffilStackClientOptions {
  baseUrl?: string;
}

export interface CreateOfferInput {
  product_name: string;
  product_url: string;
  affiliate_network: string;
  affiliate_link?: string | null;
}

export interface CrmContactInput {
  name?: string | null;
  company?: string | null;
  title?: string | null;
  email?: string | null;
  phone?: string | null;
  website?: string | null;
  location?: string | null;
  notes?: string | null;
}

export interface BulkCrmContactsResult {
  created: Record<string, unknown>[];
  failed: { index: number; errors: Record<string, string[]> }[];
}

export class AffilStackClient {
  constructor(apiKey: string, options?: AffilStackClientOptions);

  me(): Promise<Record<string, unknown>>;

  listOffers(opts?: { perPage?: number }): Promise<Record<string, unknown>>;
  getOffer(offerId: number | string): Promise<Record<string, unknown>>;
  createOffer(offer: CreateOfferInput, opts?: { idempotencyKey?: string }): Promise<Record<string, unknown>>;

  listGenerations(opts?: { offerId?: number | string; module?: string; perPage?: number }): Promise<Record<string, unknown>>;
  getGeneration(generationId: number | string): Promise<Record<string, unknown>>;

  listCrmContacts(opts?: { perPage?: number }): Promise<Record<string, unknown>>;
  getCrmContact(contactId: number | string): Promise<Record<string, unknown>>;
  createCrmContact(contact: CrmContactInput, opts?: { idempotencyKey?: string }): Promise<Record<string, unknown>>;
  bulkCreateCrmContacts(contacts: CrmContactInput[]): Promise<BulkCrmContactsResult>;
  updateCrmContact(contactId: number | string, changes: Partial<CrmContactInput> & { status?: string }): Promise<Record<string, unknown>>;

  getReferralsSummary(): Promise<Record<string, unknown>>;
}
