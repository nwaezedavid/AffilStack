import { ApiError, SignedOutError, api } from '@/lib/api';
import { loadTokens, resetTokenCacheForTests, saveTokens } from '@/lib/token-store';

jest.mock('expo-secure-store', () => {
  const store = new Map<string, string>();

  return {
    AFTER_FIRST_UNLOCK_THIS_DEVICE_ONLY: 0,
    getItemAsync: jest.fn(async (key: string) => store.get(key) ?? null),
    setItemAsync: jest.fn(async (key: string, value: string) => void store.set(key, value)),
    deleteItemAsync: jest.fn(async (key: string) => void store.delete(key)),
  };
});

jest.mock('expo-device', () => ({ deviceName: 'Test phone', modelName: 'Test' }));

type Call = { url: string; init: RequestInit };

function json(status: number, body: unknown): Response {
  return { ok: status >= 200 && status < 300, status, json: async () => body } as Response;
}

describe('api()', () => {
  let calls: Call[];

  beforeEach(() => {
    resetTokenCacheForTests();
    calls = [];
  });

  it('sends the bearer token', async () => {
    await saveTokens({ accessToken: 'access-1', refreshToken: 'refresh-1', expiresAt: Date.now() + 600_000, deviceId: 7 });
    globalThis.fetch = jest.fn(async (url: string, init: RequestInit) => {
      calls.push({ url, init });

      return json(200, { ok: true });
    }) as unknown as typeof fetch;

    await expect(api('/v1/mobile/home')).resolves.toEqual({ ok: true });
    expect(calls[0].url).toBe('https://app.affilstack.com/api/v1/mobile/home');
    expect((calls[0].init.headers as Record<string, string>).Authorization).toBe('Bearer access-1');
  });

  it('refreshes an expired token once, even for simultaneous requests', async () => {
    await saveTokens({ accessToken: 'old', refreshToken: 'refresh-old', expiresAt: Date.now() - 1000, deviceId: 7 });
    globalThis.fetch = jest.fn(async (url: string, init: RequestInit) => {
      calls.push({ url, init });

      if (url.endsWith('/api/oauth/token')) {
        return json(200, { access_token: 'new', refresh_token: 'refresh-new', expires_in: 3600, device_id: 7 });
      }

      return json(200, { ok: true });
    }) as unknown as typeof fetch;

    await Promise.all([api('/v1/mobile/home'), api('/v1/mobile/devices'), api('/v1/me')]);

    const refreshes = calls.filter((call) => call.url.endsWith('/api/oauth/token'));
    expect(refreshes).toHaveLength(1);
    expect(JSON.parse(String(refreshes[0].init.body))).toMatchObject({ grant_type: 'refresh_token', refresh_token: 'refresh-old', client_id: 'affilstack-mobile' });
    expect((await loadTokens())?.accessToken).toBe('new');
  });

  it('signs out when the refresh token is rejected', async () => {
    await saveTokens({ accessToken: 'old', refreshToken: 'revoked', expiresAt: Date.now() - 1000, deviceId: 7 });
    globalThis.fetch = jest.fn(async () => json(400, { error: 'invalid_grant' })) as unknown as typeof fetch;

    await expect(api('/v1/mobile/home')).rejects.toBeInstanceOf(SignedOutError);
    expect(await loadTokens()).toBeNull();
  });

  it('turns validation errors into an ApiError with field messages', async () => {
    await saveTokens({ accessToken: 'access', refreshToken: 'refresh', expiresAt: Date.now() + 600_000, deviceId: 7 });
    globalThis.fetch = jest.fn(async () => json(422, { message: 'Invalid.', errors: { password: ['That password is not correct.'] } })) as unknown as typeof fetch;

    const error = (await api('/v1/mobile/account', { method: 'DELETE', body: { password: 'x' } }).catch((exception: unknown) => exception)) as ApiError;

    expect(error).toBeInstanceOf(ApiError);
    expect(error.errors.password[0]).toBe('That password is not correct.');
  });

  it('calls /mobile/config without a token', async () => {
    globalThis.fetch = jest.fn(async (url: string, init: RequestInit) => {
      calls.push({ url, init });

      return json(200, { versions: {} });
    }) as unknown as typeof fetch;

    await api('/v1/mobile/config', { auth: false });
    expect((calls[0].init.headers as Record<string, string>).Authorization).toBeUndefined();
  });
});
