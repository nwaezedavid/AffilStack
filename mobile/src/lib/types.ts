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
  links: Record<'dashboard' | 'billing' | 'forgot_password' | 'privacy' | 'terms', string>;
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
