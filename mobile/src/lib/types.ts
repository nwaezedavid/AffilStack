/**
 * Shapes of the AffilStack API responses the app uses (see
 * resources/openapi/openapi.yaml, "Mobile app" tag).
 */
export type Platform = 'ios' | 'android' | 'web' | 'unknown';

export type MobileConfig = {
  brand: { name: string; icon_url: string | null };
  versions: Record<'ios' | 'android', { minimum: string; latest: string | null }>;
  store_urls: { ios: string | null; android: string | null };
  announcement: { message: string; level: 'info' | 'warning' | 'critical'; url: string | null } | null;
  auth: { client_id: string; authorize_url: string; token_url: string; revoke_url: string };
  links: Record<'dashboard' | 'forgot_password' | 'privacy' | 'terms', string>;
  features: { in_app_purchases: boolean };
};

export type Plan = {
  name: string | null;
  slug: string | null;
  status: string;
  is_trial: boolean;
  trial_ends_at: string | null;
  renews_at: string | null;
  ends_at: string | null;
};

export type MobileAccount = {
  id: number;
  name: string;
  email: string;
  avatar_url: string | null;
  initials: string;
  locale: string | null;
  is_seat: boolean;
  seat_role: string | null;
  on_shared_team_plan: boolean;
  two_factor_enabled: boolean;
  credits_balance: number;
  plan: Plan | null;
};

export type ProgressTile = {
  key: string;
  label: string;
  value: string;
  raw: number;
  week: number;
  icon: string;
  tone: 'violet' | 'brand' | 'emerald' | 'gold' | 'rose' | string;
  note: string | null;
};

export type MobileHome = {
  account: MobileAccount;
  wallet: { balance_minor: number; currency: string; formatted: string };
  progress: { tiles: ProgressTile[]; total: number; milestone: { next: number; previous: number; percent: number } };
  agents: { pending_approvals: number };
};

export type MobileDevice = {
  id: number;
  name: string | null;
  platform: Platform;
  app_version: string | null;
  os_version: string | null;
  has_push: boolean;
  is_current: boolean;
  signed_in_at: string | null;
  last_seen_at: string | null;
};

export type TokenSet = {
  accessToken: string;
  refreshToken: string;
  /** Epoch milliseconds when the access token stops working. */
  expiresAt: number;
  deviceId: number | null;
};

export type Paginated<T> = {
  data: T[];
  meta?: { current_page: number; last_page: number; total: number };
};

export type Offer = {
  id: number;
  product_name: string;
  product_url: string;
  affiliate_network: string | null;
  status: string;
  created_at: string | null;
  generations_count?: number;
  research?: {
    ideal_customer: string | null;
    where_to_find: string | null;
    recommended_channel: string | null;
    recommended_angle: string | null;
  };
};

export type GenerationStatus = 'queued' | 'processing' | 'completed' | 'failed' | string;

export type Generation = {
  id: number;
  offer_id: number | null;
  offer_name: string | null;
  module: string;
  status: GenerationStatus;
  title: string | null;
  text: string | null;
  plain_text: string | null;
  video_url: string | null;
  video_style: string | null;
  credits_spent: number;
  error: string | null;
  created_at: string | null;
};

export type ContentType = { type: string; label: string; description: string; credits: number; available: boolean };

export type OfferDetail = { offer: Offer; content: Generation[]; content_types: ContentType[] };

export type VideoStyle = {
  style: string;
  label: string;
  description: string;
  credits: number;
  duration_seconds: number;
  uses_product_photo: boolean;
  available: boolean;
};

export type AgentActivity = {
  id: number;
  agent: string;
  agent_name: string;
  action: string;
  summary: string;
  status: string;
  needs_approval: boolean;
  approve_label: string;
  created_at: string | null;
};

export type AgentsOverview = {
  included: boolean;
  approvals: AgentActivity[];
  activity: AgentActivity[];
  week: Record<string, number>;
};

export type Conversation = {
  contact_id: number;
  contact_name: string | null;
  contact_company: string | null;
  subject: string | null;
  snippet: string;
  received_at: string | null;
  unread_count: number;
  has_suggestion: boolean;
};

export type ConversationDetail = {
  contact: { id: number; name: string; email: string | null; company: string | null };
  reply_id: number;
  reply_subject: string;
  messages: { direction: 'in' | 'out'; at: string | null; subject: string | null; body: string }[];
  suggestion: { body: string; auto_send_at: string | null } | null;
  draft_cost: number;
  credits_balance: number;
  email_connected: boolean;
};

export type ContactStatus = 'new' | 'contacted' | 'qualified' | 'customer' | 'unqualified';

export type Contact = {
  id: number;
  name: string;
  company: string | null;
  title: string | null;
  email: string | null;
  phone: string | null;
  website: string | null;
  location: string | null;
  status: ContactStatus;
  tags: string[];
  notes: string | null;
  lead_score: number | null;
  source: string | null;
  lists?: { id: number; name: string }[];
  created_at: string | null;
};

export type NotificationCategory = {
  key: string;
  label: string;
  description: string;
  enabled: boolean;
};

export type NotificationPreferences = {
  push_available: boolean;
  this_device_registered: boolean;
  categories: NotificationCategory[];
};

export type BillingOverview = {
  account: MobileAccount;
  credits: { balance: number; monthly_allowance: number | null };
  wallet: { balance_minor: number; currency: string; formatted: string };
  shows_history: boolean;
  credit_activity: { id: number; label: string; amount: number; balance_after: number; created_at: string | null }[];
  wallet_activity: { id: number; label: string; amount_minor: number; formatted: string; created_at: string | null }[];
};
