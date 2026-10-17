import type { ReactNode } from 'react';

type Tone = 'success' | 'error' | 'warning' | 'info';

const TONES: Record<Tone, string> = {
  success: 'border-t-success-600',
  error: 'border-t-signal',
  warning: 'border-t-ink',
  info: 'border-t-ink',
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
      className={`border-t-[3px] pt-2.5 pb-1 text-sm ${TONES[tone]}`}
    >
      <strong className={tone === 'error' ? 'text-signal-700' : undefined}>{LABELS[tone]}: </strong>
      {children}
    </div>
  );
}
