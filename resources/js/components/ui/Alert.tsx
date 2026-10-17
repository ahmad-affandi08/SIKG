import type { ReactNode } from 'react';

type Tone = 'success' | 'error' | 'warning' | 'info';

const TONES: Record<Tone, string> = {
  success: 'bg-green-50 text-green-900 ring-green-200 border-l-success-600',
  error: 'bg-rose-50 text-rose-900 ring-rose-200 border-l-danger-600',
  warning: 'bg-amber-50 text-amber-950 ring-amber-200 border-l-warning-500',
  info: 'bg-brand-50 text-brand-800 ring-brand-200 border-l-brand-600',
};

const LABELS: Record<Tone, string> = {
  success: 'Berhasil',
  error: 'Galat',
  warning: 'Perhatian',
  info: 'Info',
};

interface AlertProps {
  tone: Tone;
  children: ReactNode;
}

/** Status tidak hanya lewat warna: label teks selalu ada. Galat memakai role=alert, sisanya role=status. */
export function Alert({ tone, children }: AlertProps) {
  return (
    <div
      role={tone === 'error' ? 'alert' : 'status'}
      className={`rounded-control border-l-4 px-4 py-3 text-sm ring-1 ${TONES[tone]}`}
    >
      <strong>{LABELS[tone]}: </strong>
      {children}
    </div>
  );
}
