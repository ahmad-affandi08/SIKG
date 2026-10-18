import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { flushQueue, useQueueItems } from '@/lib/offline/useOfflineStatus';
import { queueSnapshot, wipeQueue } from '@/lib/offline/store';

/**
 * Tombol Keluar. Antrean offline dan kunci enkripsinya selalu dibuang dari perangkat saat keluar (P3-02, M11), supaya pengguna
 * berikutnya di perangkat bersama tidak menemukan data klinis. Bila masih ada catatan yang belum terkirim, pengguna diberi tahu
 * lebih dulu: aplikasi mencoba mengirimnya selagi tersambung, dan sisanya baru dibuang setelah pengguna menyetujui.
 */
export function LogoutButton({ onClick }: { onClick?: () => void }) {
  const { auth } = usePage().props;
  const items = useQueueItems();
  const [asking, setAsking] = useState(false);
  const [busy, setBusy] = useState(false);

  async function leave() {
    setBusy(true);
    try {
      await wipeQueue();
    } finally {
      router.post('/logout');
    }
  }

  async function press() {
    onClick?.();
    if (items.length === 0) {
      await leave();
      return;
    }
    setBusy(true);
    await flushQueue(auth.user?.id ?? null);
    setBusy(false);
    if (queueSnapshot().length === 0) {
      await leave();
      return;
    }
    setAsking(true);
  }

  const remaining = items.length;

  return (
    <>
      <button
        type="button"
        className="menubar-item"
        disabled={busy}
        onClick={() => {
          void press();
        }}
      >
        Keluar
      </button>
      <ConfirmDialog
        open={asking}
        variant="danger"
        title="Ada catatan offline yang belum terkirim"
        message={`${String(remaining)} catatan belum terkirim ke server. Bila Anda keluar sekarang, catatan itu dibuang dari perangkat ini dan tidak dapat dipulihkan. Batalkan untuk menunggu sampai tersambung dan terkirim.`}
        confirmLabel="Buang dan keluar"
        cancelLabel="Batal"
        onConfirm={() => {
          setAsking(false);
          void leave();
        }}
        onCancel={() => {
          setAsking(false);
        }}
      />
    </>
  );
}
