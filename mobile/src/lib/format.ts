/** "5 Nov 2026" in the phone's language. */
export function formatDate(iso: string | null | undefined): string {
  if (!iso) return '';

  return new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

/** Whole days from now until $iso (0 when it's today or past). */
export function daysUntil(iso: string | null | undefined): number {
  if (!iso) return 0;

  return Math.max(0, Math.ceil((new Date(iso).getTime() - Date.now()) / 86_400_000));
}

export function greeting(date = new Date()): string {
  const hour = date.getHours();

  return hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
}

export function relativeTime(iso: string | null | undefined): string {
  if (!iso) return 'never';

  const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60_000);

  if (minutes < 2) return 'just now';
  if (minutes < 60) return `${minutes} min ago`;
  if (minutes < 60 * 24) return `${Math.round(minutes / 60)} h ago`;

  return formatDate(iso);
}
