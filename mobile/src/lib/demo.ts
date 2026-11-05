import type { MobileConfig, MobileDevice, MobileHome } from '@/lib/types';

/**
 * Sample data for design previews and screenshots (EXPO_PUBLIC_DEMO_MODE=1).
 * Shaped exactly like the real API so screens can't tell the difference.
 * Never used in store builds.
 */
const config: MobileConfig = {
  brand: { name: 'AffilStack', icon_url: null },
  versions: { ios: { minimum: '1.0.0', latest: '1.0.0' }, android: { minimum: '1.0.0', latest: '1.0.0' } },
  store_urls: { ios: null, android: null },
  announcement: { message: 'New: one-click videos now support three styles.', level: 'info', url: null },
  auth: {
    client_id: 'affilstack-mobile',
    authorize_url: 'https://app.affilstack.com/mobile/authorize',
    token_url: 'https://app.affilstack.com/api/oauth/token',
    revoke_url: 'https://app.affilstack.com/api/oauth/revoke',
  },
  links: {
    dashboard: 'https://app.affilstack.com/dashboard',
    billing: 'https://app.affilstack.com/billing',
    forgot_password: 'https://app.affilstack.com/forgot-password',
    privacy: 'https://affilstack.com/privacy',
    terms: 'https://affilstack.com/terms',
  },
  features: { in_app_purchases: false },
};

const home: MobileHome = {
  account: {
    id: 1,
    name: 'Ada Okafor',
    email: 'ada@example.com',
    avatar_url: null,
    initials: 'AO',
    locale: null,
    is_seat: false,
    seat_role: null,
    on_shared_team_plan: false,
    two_factor_enabled: true,
    credits_balance: 1240,
    plan: {
      name: 'Growth',
      slug: 'growth',
      status: 'trialing',
      is_trial: true,
      trial_ends_at: new Date(Date.now() + 5 * 86400000).toISOString(),
      renews_at: null,
      ends_at: null,
    },
  },
  wallet: { balance_minor: 1850000, currency: 'NGN', formatted: '₦18,500.00' },
  progress: {
    tiles: [
      { key: 'products', label: 'Products researched', value: '12', raw: 12, week: 3, icon: 'magnifying-glass-circle', tone: 'violet', note: null },
      { key: 'content', label: 'Content pieces created', value: '86', raw: 86, week: 14, icon: 'document-text', tone: 'brand', note: null },
      { key: 'prospects', label: 'Prospects found', value: '342', raw: 342, week: 57, icon: 'users', tone: 'emerald', note: null },
      { key: 'emails', label: 'Emails sent for you', value: '158', raw: 158, week: 21, icon: 'paper-airplane', tone: 'brand', note: null },
      { key: 'clicks', label: 'Link clicks tracked', value: '2,914', raw: 2914, week: 403, icon: 'cursor-arrow-rays', tone: 'gold', note: null },
      { key: 'sales', label: 'Sales tracked', value: '27', raw: 27, week: 4, icon: 'banknotes', tone: 'rose', note: '$1,380 in commissions' },
      { key: 'hours', label: 'Hours of work saved (estimate)', value: '96', raw: 96, week: 11, icon: 'clock', tone: 'gold', note: null },
    ],
    total: 3539,
    milestone: { next: 5000, previous: 2500, percent: 42 },
  },
  agents: { pending_approvals: 3 },
};

const devices: MobileDevice[] = [
  { id: 1, name: 'iPhone 16', platform: 'ios', app_version: '1.0.0', os_version: '19.1', has_push: true, is_current: true, signed_in_at: new Date().toISOString(), last_seen_at: new Date().toISOString() },
  { id: 2, name: 'Pixel 9', platform: 'android', app_version: '1.0.0', os_version: '16', has_push: true, is_current: false, signed_in_at: new Date(Date.now() - 9 * 86400000).toISOString(), last_seen_at: new Date(Date.now() - 2 * 86400000).toISOString() },
];

export async function demoResponse<T>(method: string, path: string, _body?: unknown): Promise<T> {
  await new Promise((resolve) => setTimeout(resolve, 250));

  if (path === '/v1/mobile/config') return config as T;
  if (path === '/v1/mobile/home') return home as T;
  if (path === '/v1/mobile/devices' && method === 'GET') return { data: devices } as T;
  if (path === '/v1/mobile/devices/current') return { data: devices[0] } as T;

  return { message: 'Done.' } as T;
}

export const demoConfig = config;
