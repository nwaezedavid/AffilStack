/**
 * Compares dotted version numbers ("1.2.0" vs "1.10"). Missing parts count
 * as 0, anything that isn't a number counts as 0.
 */
export function compareVersions(a: string, b: string): number {
  const left = a.split('.').map((part) => Number.parseInt(part, 10) || 0);
  const right = b.split('.').map((part) => Number.parseInt(part, 10) || 0);
  const length = Math.max(left.length, right.length, 3);

  for (let index = 0; index < length; index++) {
    const difference = (left[index] ?? 0) - (right[index] ?? 0);

    if (difference !== 0) {
      return difference > 0 ? 1 : -1;
    }
  }

  return 0;
}

export function isBelowMinimum(current: string, minimum: string | null | undefined): boolean {
  return Boolean(minimum) && compareVersions(current, minimum as string) < 0;
}
