import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState, useSyncExternalStore } from 'react';

import { offlineQueue, syncNow } from '@/lib/offline/service';
import { queueSnapshot, refreshQueue, subscribeQueue } from '@/lib/offline/store';
import { OFFLINE_QUEUED_EVENT } from '@/lib/offline/submit';

export interface OfflineStatus {
  online: boolean;
  pending: number;
  flagged: number;
  notice: string | null;
}

const SYNC_INTERVAL_MS = 60_000;

function subscribeOnline(onChange: () => void): () => void {
  window.addEventListener('online', onChange);
  window.addEventListener('offline', onChange);
  return () => {
    window.removeEventListener('online', onChange);
    window.removeEventListener('offline', onChange);
  };
}

/** Isi antrean offline pengguna (tanpa isi catatan) dari penyimpan memori. */
export function useQueueItems() {
  return useSyncExternalStore(subscribeQueue, queueSnapshot, queueSnapshot);
}

/** Mengirim antrean milik pengguna lalu memperbarui tampilan; mengembalikan jumlah terkirim. */
export async function flushQueue(userId: string | null): Promise<number> {
  if (userId === null || !navigator.onLine || offlineQueue() === null) {
    return 0;
  }
  const result = await syncNow(userId);
  await refreshQueue(userId);
  return result.sent;
}

/** Status koneksi dan antrean offline; mengirim antrean otomatis begitu tersambung dan tiap menit. */
export function useOfflineStatus(): OfflineStatus {
  const { auth } = usePage().props;
  const userId = auth.user?.id ?? null;
  const online = useSyncExternalStore(
    subscribeOnline,
    () => navigator.onLine,
    () => true,
  );
  const [notice, setNotice] = useState<string | null>(null);
  const items = useQueueItems();

  const sync = useCallback(async () => {
    const sent = await flushQueue(userId);
    if (sent > 0) {
      setNotice(`${String(sent)} catatan offline terkirim.`);
    }
  }, [userId]);

  useEffect(() => {
    const queued = (event: Event) => {
      setNotice(
        `${(event as CustomEvent<string>).detail} disimpan di perangkat dan dikirim saat tersambung.`,
      );
      void refreshQueue(userId);
    };
    const reconnect = () => {
      void sync();
    };
    window.addEventListener('online', reconnect);
    window.addEventListener(OFFLINE_QUEUED_EVENT, queued);
    const timer = window.setInterval(reconnect, SYNC_INTERVAL_MS);
    void refreshQueue(userId).then(reconnect);
    return () => {
      window.removeEventListener('online', reconnect);
      window.removeEventListener(OFFLINE_QUEUED_EVENT, queued);
      window.clearInterval(timer);
    };
  }, [sync, userId]);

  return {
    online,
    pending: items.filter((item) => item.status === 'pending').length,
    flagged: items.filter((item) => item.status !== 'pending').length,
    notice,
  };
}
