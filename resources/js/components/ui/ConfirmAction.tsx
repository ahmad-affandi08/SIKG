import { useState } from 'react';

import { Button } from '@/components/ui/Button';

interface ConfirmActionProps {
  label: string;
  /** Kalimat yang menyebut akibat aksi (docs/rules/frontend.md bagian 8). */
  message: string;
  confirmLabel?: string;
  variant?: 'primary' | 'secondary' | 'danger';
  onConfirm: () => void;
}

/** Aksi merusak atau keuangan selalu meminta konfirmasi yang menyebut akibatnya. */
export function ConfirmAction({
  label,
  message,
  confirmLabel = 'Ya, lanjutkan',
  variant = 'danger',
  onConfirm,
}: ConfirmActionProps) {
  const [asking, setAsking] = useState(false);

  if (!asking) {
    return (
      <Button
        variant="secondary"
        onClick={() => {
          setAsking(true);
        }}
      >
        {label}
      </Button>
    );
  }

  return (
    <div
      role="alertdialog"
      aria-label={label}
      className="rounded-control flex flex-wrap items-center gap-2 bg-amber-50 p-2 text-sm"
    >
      <span>{message}</span>
      <Button
        variant={variant}
        onClick={() => {
          setAsking(false);
          onConfirm();
        }}
      >
        {confirmLabel}
      </Button>
      <Button
        variant="secondary"
        onClick={() => {
          setAsking(false);
        }}
      >
        Batal
      </Button>
    </div>
  );
}
