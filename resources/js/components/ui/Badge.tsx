import type { ReactNode } from 'react';

type Tone = 'neutral' | 'success' | 'danger' | 'warning';

const TONES: Record<Tone, string> = {
  neutral: 'bg-slate-100 text-slate-700 ring-slate-200',
  success: 'bg-green-50 text-green-800 ring-green-200',
  danger: 'bg-rose-50 text-rose-800 ring-rose-200',
  warning: 'bg-amber-50 text-amber-900 ring-amber-200',
};

export function Badge({ tone = 'neutral', children }: { tone?: Tone; children: ReactNode }) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${TONES[tone]}`}
    >
      {children}
    </span>
  );
}
