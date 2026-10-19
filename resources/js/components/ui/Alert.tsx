import type { ReactNode } from 'react';

type Tone = 'success' | 'error' | 'warning' | 'info';

const TONES: Record<Tone, string> = {
  success: 'border-success-600 bg-[#eaf6ee]',
  error: 'border-danger-600 bg-[#fdecea]',
  warning: 'border-warning-500 bg-[#fff6d6]',
  info: 'border-brand-600 bg-brand-50',
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
      className={`rounded-lg border px-3 py-2 text-sm ${TONES[tone]}`}
    >
      <strong className={tone === 'error' ? 'text-signal-700' : undefined}>{LABELS[tone]}: </strong>
      {children}
    </div>
  );
}
