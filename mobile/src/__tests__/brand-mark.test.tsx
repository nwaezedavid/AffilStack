import { brandInitials } from '@/components/brand-mark';

describe('brandInitials', () => {
  it('uses the capitals of the brand name', () => {
    expect(brandInitials('AffilStack')).toBe('AS');
  });

  it('falls back to the first two letters', () => {
    expect(brandInitials('acme')).toBe('AC');
  });
});
