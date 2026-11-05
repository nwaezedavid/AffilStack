import { compareVersions, isBelowMinimum } from '@/lib/version';

describe('compareVersions', () => {
  it('orders dotted versions numerically', () => {
    expect(compareVersions('1.10.0', '1.9.9')).toBe(1);
    expect(compareVersions('1.2', '1.2.0')).toBe(0);
    expect(compareVersions('0.9.0', '1.0.0')).toBe(-1);
  });
});

describe('isBelowMinimum', () => {
  it('forces an update only when the app is older than the minimum', () => {
    expect(isBelowMinimum('1.0.0', '1.2.0')).toBe(true);
    expect(isBelowMinimum('1.2.0', '1.2.0')).toBe(false);
    expect(isBelowMinimum('1.3.0', '1.2.0')).toBe(false);
    expect(isBelowMinimum('1.0.0', null)).toBe(false);
  });
});
