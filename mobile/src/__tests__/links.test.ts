import { appPathForUrl, routeForSystemPath } from '@/lib/links';

describe('appPathForUrl()', () => {
  it.each([
    ['https://app.affilstack.com/dashboard', '/'],
    ['https://app.affilstack.com/offers', '/products'],
    ['https://app.affilstack.com/offers/create', '/products/new'],
    ['https://app.affilstack.com/offers/42', '/products/42'],
    ['https://app.affilstack.com/offers/42/features/blog', '/products/42'],
    ['https://app.affilstack.com/agents', '/agents'],
    ['https://app.affilstack.com/crm', '/contacts'],
    ['https://app.affilstack.com/crm/inbox', '/inbox'],
    ['https://app.affilstack.com/crm/inbox/501?tab=thread', '/inbox/501'],
  ])('opens %s on the app screen %s', (url, path) => {
    expect(appPathForUrl(url)).toBe(path);
  });

  it.each([
    'https://app.affilstack.com/support/12',
    'https://app.affilstack.com/billing',
    'https://app.affilstack.com/mobile/authorize?client_id=affilstack-mobile',
    'https://evil.example/offers/42',
    'javascript:alert(1)',
    'not a url',
    '',
  ])('leaves %s to the browser', (url) => {
    expect(appPathForUrl(url)).toBeNull();
  });
});

describe('routeForSystemPath()', () => {
  it('turns universal links into app screens', () => {
    expect(routeForSystemPath('https://app.affilstack.com/crm/inbox/7')).toBe('/inbox/7');
    expect(routeForSystemPath('https://app.affilstack.com/support/12')).toBe('/');
  });

  it('keeps the sign-in return away from the router', () => {
    expect(routeForSystemPath('affilstack://auth/callback?code=abc&state=xyz')).toBe('/');
  });

  it('passes app paths through', () => {
    expect(routeForSystemPath('/products/3')).toBe('/products/3');
  });
});
