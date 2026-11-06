import { routeForSystemPath } from '@/lib/links';

/**
 * Links the phone opens the app with: AffilStack web addresses (Universal
 * Links / App Links) are turned into the matching app screen.
 */
export function redirectSystemPath({ path }: { path: string; initial: boolean }): string {
  return routeForSystemPath(path);
}
