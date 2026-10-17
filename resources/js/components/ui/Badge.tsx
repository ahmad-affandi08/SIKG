import type { ReactNode } from 'react';

type Tone = 'neutral' | 'success' | 'danger' | 'warning';

/** Penanda status: titik di depan teks. Maknanya selalu ada pada teks, tidak hanya warna. */
const DOTS: Record<Tone, string> = {
  neutral: 'border-2 border-ink bg-white',
  success: 'bg-success-600',
  danger: 'bg-signal',
  warning: 'bg-warning-500',
};

export function Badge({ tone = 'neutral', children }: { tone?: Tone; children: ReactNode }) {
  return (
    <span className="inline-flex items-center gap-1.5 text-sm font-medium whitespace-nowrap">
      <span
        aria-hidden="true"
        className={`inline-block size-2.5 shrink-0 rounded-full ${DOTS[tone]}`}
      />
      {children}
    </span>
  );
}
