import { useEffect, useId, useRef } from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';

import { Button } from '@/components/ui/Button';

interface ConfirmDialogProps {
  open: boolean;
  title: string;
  /** Kalimat yang menyebut akibat aksi (docs/rules/frontend.md bagian 8). */
  message: ReactNode;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: 'primary' | 'danger';
  onConfirm: () => void;
  onCancel: () => void;
}

/**
 * Jendela konfirmasi di tengah layar dengan latar redup. Fokus awal ada pada tombol Batal untuk aksi berbahaya (supaya
 * Enter tidak langsung menyetujui) dan pada tombol setuju untuk aksi biasa; Tab berputar di dalam jendela, Esc dan klik
 * latar membatalkan, dan fokus kembali ke pemicunya setelah ditutup.
 */
export function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel = 'Ya, lanjutkan',
  cancelLabel = 'Batal',
  variant = 'primary',
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  const titleId = useId();
  const messageId = useId();
  const confirmRef = useRef<HTMLButtonElement>(null);
  const cancelRef = useRef<HTMLButtonElement>(null);

  const cancelHandler = useRef(onCancel);
  useEffect(() => {
    cancelHandler.current = onCancel;
  });

  useEffect(() => {
    if (!open) {
      return undefined;
    }
    const previous = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    (variant === 'danger' ? cancelRef : confirmRef).current?.focus();

    function onKeyDown(event: globalThis.KeyboardEvent) {
      if (event.key === 'Escape') {
        event.stopPropagation();
        cancelHandler.current();
        return;
      }
      if (event.key !== 'Tab') {
        return;
      }
      const first = cancelRef.current;
      const last = confirmRef.current;
      if (!first || !last) {
        return;
      }
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
    document.addEventListener('keydown', onKeyDown);

    return () => {
      document.removeEventListener('keydown', onKeyDown);
      previous?.focus();
    };
  }, [open, variant]);

  if (!open) {
    return null;
  }

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3">
      <button
        type="button"
        tabIndex={-1}
        aria-label="Tutup jendela konfirmasi"
        className="absolute inset-0 cursor-default bg-black/40"
        onClick={onCancel}
      />
      <div
        role="alertdialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={messageId}
        className="pane relative w-full max-w-md shadow-lg"
      >
        <h2 id={titleId} className="pane-head">
          {title}
        </h2>
        <p id={messageId} className="p-3 text-sm">
          {message}
        </p>
        <div className="flex flex-wrap justify-end gap-2 border-t border-[var(--color-line)] p-3">
          <Button ref={cancelRef} variant="secondary" onClick={onCancel}>
            {cancelLabel}
          </Button>
          <Button ref={confirmRef} variant={variant} onClick={onConfirm}>
            {confirmLabel}
          </Button>
        </div>
      </div>
    </div>,
    document.body,
  );
}
