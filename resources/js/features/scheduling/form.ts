import { DURATIONS } from '@/features/scheduling/schemas';

export const DURATION_OPTIONS = DURATIONS.map((d) => ({
  value: String(d),
  label: `${String(d)} menit`,
}));

export function collectErrors(
  issues: readonly { path: PropertyKey[]; message: string }[],
): Record<string, string> {
  const next: Record<string, string> = {};
  for (const issue of issues) {
    next[String(issue.path[0])] ??= issue.message;
  }
  return next;
}
