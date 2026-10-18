import { useState } from 'react';

import { Button } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';

interface ConfirmActionProps {
  label: string;
  /** Kalimat yang menyebut akibat aksi (docs/rules/frontend.md bagian 8). */
  message: string;
  confirmLabel?: string;
  variant?: 'primary' | 'secondary' | 'danger';
  onConfirm: () => void;
}

/** Aksi merusak atau keuangan selalu meminta konfirmasi (jendela di tengah layar) yang menyebut akibatnya. */
export function ConfirmAction({
  label,
  message,
  confirmLabel = 'Ya, lanjutkan',
  variant = 'danger',
  onConfirm,
}: ConfirmActionProps) {
  const [asking, setAsking] = useState(false);

  return (
    <>
      <Button
        variant="secondary"
        onClick={() => {
          setAsking(true);
        }}
      >
        {label}
      </Button>
      <ConfirmDialog
        open={asking}
        title={label}
        message={message}
        confirmLabel={confirmLabel}
        variant={variant === 'danger' ? 'danger' : 'primary'}
        onCancel={() => {
          setAsking(false);
        }}
        onConfirm={() => {
          setAsking(false);
          onConfirm();
        }}
      />
    </>
  );
}
