import { sendQueued } from '@/lib/offline/api';
import { idbStore } from '@/lib/offline/idb';
import { OfflineQueue } from '@/lib/offline/queue';

let instance: OfflineQueue | null = null;

/** Antrean tunggal untuk aplikasi; null bila peramban tidak mendukung penyimpanan atau Web Crypto. */
export function offlineQueue(): OfflineQueue | null {
  if (instance) {
    return instance;
  }
  if (typeof indexedDB === 'undefined' || typeof crypto === 'undefined' || !('subtle' in crypto)) {
    return null;
  }
  instance = new OfflineQueue(idbStore, crypto);
  return instance;
}

export async function syncNow(
  userId: string,
): Promise<{ sent: number; flagged: number; stopped: boolean }> {
  const queue = offlineQueue();
  if (!queue) {
    return { sent: 0, flagged: 0, stopped: false };
  }
  return queue.sync(userId, sendQueued);
}
