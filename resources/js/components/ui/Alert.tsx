import type { ReactNode } from 'react';

type Tone = 'success' | 'error' | 'warning' | 'info';

const TONES: Record<Tone, string> = {
  success: 'bg-green-50 text-success-600 ring-success-600',
  error: 'bg-red-50 text-danger-600 ring-danger-600',
  warning: 'bg-amber-50 text-slate-900 ring-warning-500',
  info: 'bg-brand-50 text-brand-700 ring-brand-500',
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
      className={`rounded-control p-3 text-sm ring-1 ${TONES[tone]}`}
    >
      <strong>{LABELS[tone]}: </strong>
      {children}
    </div>
  );
}
