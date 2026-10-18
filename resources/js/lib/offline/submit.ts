import { router } from '@inertiajs/react';
import type { VisitOptions } from '@inertiajs/core';

import { isQueueable } from '@/lib/offline/allowlist';
import { offlineQueue } from '@/lib/offline/service';

export const OFFLINE_QUEUED_EVENT = 'sikg:offline-queued';

/**
 * Menyimpan perubahan lewat Inertia. Saat peramban offline dan aksinya termasuk daftar aman (anamnesis, catatan SOAP), perubahan
 * diantrekan terenkripsi di perangkat dan dikirim otomatis setelah tersambung; selain itu, atau saat online, perilaku biasa.
 *
 * @returns true bila diantrekan (bukan dikirim)
 */
export async function putOrQueue(
  userId: string | null,
  url: string,
  data: Record<string, unknown>,
  options: VisitOptions,
): Promise<boolean> {
  const offline = typeof navigator !== 'undefined' && !navigator.onLine;
  const queue = offline && userId !== null && isQueueable('PUT', url) ? offlineQueue() : null;
  if (queue && userId !== null) {
    const item = await queue.enqueue(userId, 'PUT', url, data);
    if (item) {
      window.dispatchEvent(new CustomEvent(OFFLINE_QUEUED_EVENT, { detail: item.label }));
      options.onFinish?.({} as never);
      return true;
    }
  }
  router.put(url, data as never, options);
  return false;
}
