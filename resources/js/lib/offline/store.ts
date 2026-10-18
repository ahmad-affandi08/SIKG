import { offlineQueue } from '@/lib/offline/service';
import type { QueuedRequest } from '@/lib/offline/queue';

export type QueueItem = Omit<QueuedRequest, 'iv' | 'cipher'>;

/**
 * Salinan antrean offline di memori untuk tampilan (tanpa isi catatan). Penyimpan eksternal sederhana agar komponen tidak perlu
 * penyedia khusus; diperbarui lewat `refreshQueue` setelah menambah, mengirim, atau membuang.
 */
let snapshot: readonly QueueItem[] = [];
const listeners = new Set<() => void>();

export function subscribeQueue(listener: () => void): () => void {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
}

export function queueSnapshot(): readonly QueueItem[] {
  return snapshot;
}

/** Membuang seluruh antrean dan kunci di perangkat ini, lalu mengosongkan tampilan (dipanggil sebelum keluar akun). */
export async function wipeQueue(): Promise<void> {
  const queue = offlineQueue();
  if (queue) {
    await queue.wipe();
  }
  snapshot = [];
  listeners.forEach((listener) => {
    listener();
  });
}

/** Membuang antrean milik pengguna lain yang pernah memakai perangkat ini. */
export async function purgeOtherUsers(userId: string): Promise<void> {
  await offlineQueue()?.purgeOtherUsers(userId);
}

export async function refreshQueue(userId: string | null): Promise<void> {
  const queue = offlineQueue();
  snapshot = queue && userId !== null ? await queue.list(userId) : [];
  listeners.forEach((listener) => {
    listener();
  });
}
