import type { ReactNode } from 'react';

type Tone = 'neutral' | 'success' | 'danger' | 'warning';

const TONES: Record<Tone, string> = {
  neutral: 'bg-slate-100 text-slate-900',
  success: 'bg-green-100 text-success-600',
  danger: 'bg-red-100 text-danger-600',
  warning: 'bg-amber-100 text-slate-900',
};

export function Badge({ tone = 'neutral', children }: { tone?: Tone; children: ReactNode }) {
  return (
    <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${TONES[tone]}`}>
      {children}
    </span>
  );
}
