import type { AgentsOverview, Contact, Conversation, ConversationDetail, Generation, MobileConfig, MobileDevice, MobileHome, Offer, VideoStyle } from '@/lib/types';

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

const ago = (hours: number) => new Date(Date.now() - hours * 3600_000).toISOString();

const offers: Offer[] = [
  { id: 11, product_name: 'Notion AI Workspace', product_url: 'https://notion.so', affiliate_network: 'Impact', status: 'ready', created_at: ago(30), generations_count: 9 },
  { id: 12, product_name: 'Canva Pro', product_url: 'https://canva.com', affiliate_network: 'PartnerStack', status: 'ready', created_at: ago(80), generations_count: 14 },
  { id: 13, product_name: 'Semrush SEO Toolkit', product_url: 'https://semrush.com', affiliate_network: 'Impact', status: 'researching', created_at: ago(1), generations_count: 0 },
  { id: 14, product_name: 'Systeme.io Funnel Builder', product_url: 'https://systeme.io', affiliate_network: 'Direct', status: 'ready', created_at: ago(200), generations_count: 6 },
];

const article = 'Notion AI turns a messy workspace into a second brain. In this guide you\'ll see how founders use it to plan launches, write faster and keep the whole team on the same page — plus the one setting most people miss.';

const content: Generation[] = [
  { id: 201, offer_id: 11, offer_name: 'Notion AI Workspace', module: 'blog_article', status: 'completed', title: 'Notion AI review: is it worth it for founders?', text: `<p>${article}</p>`, plain_text: article, video_url: null, video_style: null, credits_spent: 15, error: null, created_at: ago(5) },
  { id: 202, offer_id: 11, offer_name: 'Notion AI Workspace', module: 'linkedin_post', status: 'completed', title: null, text: 'I replaced 4 apps with one workspace this month…', plain_text: 'I replaced 4 apps with one workspace this month. Here\'s what happened to my week 👇', video_url: null, video_style: null, credits_spent: 5, error: null, created_at: ago(26) },
  { id: 203, offer_id: 11, offer_name: 'Notion AI Workspace', module: 'x_thread', status: 'processing', title: null, text: null, plain_text: null, video_url: null, video_style: null, credits_spent: 0, error: null, created_at: ago(0.1) },
];

const videos: Generation[] = [
  { id: 301, offer_id: 12, offer_name: 'Canva Pro', module: 'ugc_video', status: 'processing', title: null, text: null, plain_text: null, video_url: null, video_style: 'product_showcase', credits_spent: 0, error: null, created_at: ago(0.2) },
  { id: 302, offer_id: 11, offer_name: 'Notion AI Workspace', module: 'ugc_video', status: 'completed', title: null, text: null, plain_text: null, video_url: 'https://cdn.example.com/demo.mp4', video_style: 'talking_review', credits_spent: 110, error: null, created_at: ago(20) },
  { id: 303, offer_id: 14, offer_name: 'Systeme.io Funnel Builder', module: 'ugc_video', status: 'completed', title: null, text: null, plain_text: null, video_url: 'https://cdn.example.com/demo2.mp4', video_style: 'problem_solution', credits_spent: 150, error: null, created_at: ago(60) },
];

const styles: VideoStyle[] = [
  { style: 'talking_review', label: 'Talking review', description: 'An AI presenter tells viewers why they love the product, like a real creator review.', credits: 110, duration_seconds: 50, uses_product_photo: false, available: true },
  { style: 'product_showcase', label: 'Product showcase', description: 'A short, eye-catching clip with your product on screen and a voiceover selling it.', credits: 120, duration_seconds: 10, uses_product_photo: true, available: true },
  { style: 'problem_solution', label: 'Problem → solution', description: 'Opens on a relatable problem, then reveals your product as the fix. Great for ads.', credits: 150, duration_seconds: 15, uses_product_photo: true, available: true },
];

const agents: AgentsOverview = {
  included: true,
  approvals: [
    { id: 401, agent: 'commission_watchdog', agent_name: 'Commission Watchdog', action: 'swap_link', summary: 'Your Canva Pro affiliate link returned errors 3 times today. Switch every tracked link to your working PartnerStack link?', status: 'needs_approval', needs_approval: true, approve_label: 'Approve', created_at: ago(2) },
    { id: 402, agent: 'offer_scout', agent_name: 'Offer Scout', action: 'found_program', summary: 'Found a 40% recurring program on Impact that matches your audience: Descript. Research it?', status: 'needs_approval', needs_approval: true, approve_label: 'Research it', created_at: ago(9) },
    { id: 403, agent: 'offer_scout', agent_name: 'Offer Scout', action: 'found_program', summary: 'ClickUp raised its commission to 25% recurring. Research it as a new product?', status: 'needs_approval', needs_approval: true, approve_label: 'Research it', created_at: ago(30) },
  ],
  activity: [
    { id: 410, agent: 'lead_hunter', agent_name: 'Lead Hunter', action: 'found_leads', summary: 'Added 24 new marketing agencies in Lagos to your Notion AI list.', status: 'done', needs_approval: false, approve_label: 'Approve', created_at: ago(4) },
    { id: 411, agent: 'content_autopilot', agent_name: 'Content Autopilot', action: 'wrote', summary: 'Wrote and scheduled a LinkedIn post for Canva Pro (Thursday 9am).', status: 'done', needs_approval: false, approve_label: 'Approve', created_at: ago(12) },
    { id: 412, agent: 'weekly_briefing', agent_name: 'Weekly briefing', action: 'briefing', summary: 'Your week: 403 clicks (+18%), 4 sales, 57 new prospects. Best day: Tuesday.', status: 'done', needs_approval: false, approve_label: 'Approve', created_at: ago(40) },
  ],
  week: { lead_hunter: 6, content_autopilot: 4, commission_watchdog: 1, offer_scout: 2 },
};

const conversations: Conversation[] = [
  { contact_id: 501, contact_name: 'Chidi Okeke', contact_company: 'BrightPath Media', subject: 'Re: Notion AI for your team', snippet: 'This looks great — does it work for a team of 12, and is there a discount for annual?', received_at: ago(1), unread_count: 2, has_suggestion: true },
  { contact_id: 502, contact_name: 'Sarah Mensah', contact_company: 'Lumen Studio', subject: 'Re: Quick question', snippet: 'Thanks for the guide! Booked a call for Friday.', received_at: ago(20), unread_count: 0, has_suggestion: false },
  { contact_id: 503, contact_name: 'Tunde Bakare', contact_company: null, subject: 'Re: Canva Pro templates', snippet: 'Not right now, maybe next quarter.', received_at: ago(70), unread_count: 0, has_suggestion: false },
];

const conversation: ConversationDetail = {
  contact: { id: 501, name: 'Chidi Okeke', email: 'chidi@brightpath.ng', company: 'BrightPath Media' },
  reply_id: 901,
  reply_subject: 'Re: Notion AI for your team',
  messages: [
    { direction: 'out', at: ago(30), subject: 'Notion AI for your team', body: 'Hi Chidi — saw BrightPath is growing fast. Notion AI helped agencies like yours cut planning time in half. Worth a look?' },
    { direction: 'in', at: ago(1), subject: 'Re: Notion AI for your team', body: 'This looks great — does it work for a team of 12, and is there a discount for annual?' },
  ],
  suggestion: { body: 'Hi Chidi, great question! Yes — it works well for teams of 12, and annual billing saves about 20%. Here\'s the link to start a free trial: {affiliate_link}', auto_send_at: null },
  draft_cost: 3,
  credits_balance: 1240,
  email_connected: true,
};

const contacts: Contact[] = [
  { id: 501, name: 'Chidi Okeke', company: 'BrightPath Media', title: 'Founder', email: 'chidi@brightpath.ng', phone: '+2348031234567', website: 'https://brightpath.ng', location: 'Lagos, NG', status: 'qualified', tags: ['agency', 'hot'], notes: 'Wants annual pricing for 12 seats.', lead_score: 86, source: 'google_maps', created_at: ago(300) },
  { id: 502, name: 'Sarah Mensah', company: 'Lumen Studio', title: 'Creative Director', email: 'sarah@lumen.studio', phone: '+233201234567', website: null, location: 'Accra, GH', status: 'contacted', tags: ['design'], notes: null, lead_score: 64, source: 'apollo', created_at: ago(500) },
  { id: 503, name: 'Tunde Bakare', company: null, title: null, email: 'tunde@example.com', phone: null, website: null, location: 'Abuja, NG', status: 'unqualified', tags: [], notes: 'Revisit next quarter.', lead_score: 22, source: 'manual', created_at: ago(900) },
  { id: 504, name: 'Grace Adeyemi', company: 'Peak Fitness', title: 'Owner', email: 'grace@peakfit.ng', phone: '+2348091112233', website: 'https://peakfit.ng', location: 'Ibadan, NG', status: 'new', tags: ['fitness'], notes: null, lead_score: 71, source: 'google_maps', created_at: ago(20) },
];

const notificationPreferences = {
  push_available: true,
  this_device_registered: true,
  categories: [
    { key: 'content_ready', label: 'Content & videos ready', description: 'When an article, post, script, video or product research is finished.', enabled: true },
    { key: 'agents', label: 'AI agents', description: 'When an agent needs your approval or finished something for you.', enabled: true },
    { key: 'inbox_replies', label: 'Buyer replies', description: 'When a contact replies to one of your emails.', enabled: true },
    { key: 'weekly_briefing', label: 'Weekly briefing', description: 'Your Monday summary of what AffilStack did.', enabled: false },
    { key: 'support', label: 'Support replies', description: 'When our team answers one of your tickets.', enabled: true },
    { key: 'account', label: 'Account alerts', description: 'Broken connections, paused campaigns and add-on payments.', enabled: true },
    { key: 'payouts', label: 'Payouts', description: 'When a referral payout is sent to you.', enabled: true },
  ],
};

const page = <T,>(data: T[]) => ({ data, meta: { current_page: 1, last_page: 1, total: data.length } });

export async function demoResponse<T>(method: string, path: string, body?: unknown): Promise<T> {
  await new Promise((resolve) => setTimeout(resolve, 250));

  if (path === '/v1/mobile/config') return config as T;
  if (path === '/v1/mobile/home') return home as T;
  if (path === '/v1/mobile/devices' && method === 'GET') return { data: devices } as T;
  if (path === '/v1/mobile/devices/current') return { data: devices[0] } as T;

  if (path === '/v1/mobile/notifications/preferences') {
    const changes = ((body as { categories?: Record<string, boolean> } | undefined)?.categories ?? {}) as Record<string, boolean>;
    notificationPreferences.categories = notificationPreferences.categories.map((category) => (category.key in changes ? { ...category, enabled: changes[category.key] } : category));

    return { data: notificationPreferences } as T;
  }
  if (path === '/v1/mobile/notifications/test') return { message: 'Test notification sent. It should arrive within a few seconds.' } as T;

  const route = path.split('?')[0];
  const id = Number(route.split('/')[4]);

  if (route === '/v1/mobile/offers') return page(offers) as T;
  if (/^\/v1\/mobile\/offers\/\d+$/.test(route)) {
    return {
      offer: { ...(offers.find((offer) => offer.id === id) ?? offers[0]), research: { ideal_customer: 'Founders and small agencies drowning in docs, tasks and meeting notes.', where_to_find: 'LinkedIn founder groups, r/Notion, YouTube productivity channels.', recommended_channel: 'LinkedIn', recommended_angle: 'Replace four apps with one workspace and win back an hour a day.' } },
      content: content.filter((item) => item.offer_id === id || id === 0),
      content_types: [
        { type: 'blog_article', label: 'Blog article', description: 'A full SEO article with your tracked link.', credits: 15, available: true },
        { type: 'linkedin_post', label: 'LinkedIn post', description: 'A ready-to-post update for your feed.', credits: 5, available: true },
        { type: 'linkedin_article', label: 'LinkedIn article', description: 'A long-form LinkedIn article.', credits: 12, available: true },
        { type: 'x_thread', label: 'X thread', description: 'A thread that sells without sounding salesy.', credits: 8, available: true },
        { type: 'youtube_script', label: 'Video script', description: 'A YouTube or short-video script.', credits: 15, available: false },
      ],
    } as T;
  }
  if (method === 'POST' && (route.endsWith('/content') || route.endsWith('/videos'))) return { data: { ...content[2], id: 999 } } as T;
  if (route.startsWith('/v1/mobile/generations/')) return { data: [...content, ...videos].find((item) => item.id === id) ?? content[0] } as T;
  if (route === '/v1/mobile/videos/styles') return { data: styles } as T;
  if (route === '/v1/mobile/videos') return page(videos) as T;
  if (route === '/v1/mobile/agents') return agents as T;
  if (route === '/v1/mobile/inbox') return { data: conversations, email_connected: true } as T;
  if (/^\/v1\/mobile\/inbox\/\d+$/.test(route)) return conversation as T;
  if (route.endsWith('/draft')) return { body: conversation.suggestion?.body } as T;
  if (route === '/v1/mobile/contacts') return page(contacts) as T;
  if (/^\/v1\/mobile\/contacts\/\d+$/.test(route)) return { data: contacts.find((contact) => contact.id === id) ?? contacts[0] } as T;

  return { message: 'Done.' } as T;
}

export const demoConfig = config;
