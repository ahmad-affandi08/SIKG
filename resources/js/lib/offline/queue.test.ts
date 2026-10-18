import { webcrypto } from 'node:crypto';
import { describe, expect, it } from 'vitest';

import { isQueueable, queueLabel } from './allowlist';
import { MAX_AGE_MS, OfflineQueue } from './queue';
import type { QueueStore, QueuedRequest, Sender } from './queue';

const crypto = webcrypto as unknown as Crypto;
const ENCOUNTER = '01HZ00000000000000000000AB';

function memoryStore(): QueueStore & { items: Map<string, QueuedRequest> } {
  const items = new Map<string, QueuedRequest>();
  let key: CryptoKey | null = null;
  return {
    items,
    list: () => Promise.resolve([...items.values()]),
    put: (item) => {
      items.set(item.id, item);
      return Promise.resolve();
    },
    remove: (id) => {
      items.delete(id);
      return Promise.resolve();
    },
    loadKey: () => Promise.resolve(key),
    saveKey: (created) => {
      key = created;
      return Promise.resolve();
    },
  };
}

describe('P3-02 daftar aksi yang boleh diantrekan', () => {
  it('hanya menulis ulang anamnesis dan catatan SOAP', () => {
    expect(queueLabel('PUT', `/encounters/${ENCOUNTER}/anamnesis`)).toBe('Anamnesis');
    expect(queueLabel('put', `/encounters/${ENCOUNTER}/notes`)).toBe('Catatan SOAP');
    expect(isQueueable('PUT', `/encounters/${ENCOUNTER}/notes?tab=soap`)).toBe(true);
  });

  it('menolak pembayaran, penutupan kunjungan, stok, dan metode selain PUT', () => {
    expect(isQueueable('POST', `/encounters/${ENCOUNTER}/notes`)).toBe(false);
    expect(isQueueable('POST', '/invoices/01HZ00000000000000000000AB/payments')).toBe(false);
    expect(isQueueable('POST', `/encounters/${ENCOUNTER}/close`)).toBe(false);
    expect(isQueueable('POST', '/stock/movements')).toBe(false);
    expect(isQueueable('DELETE', `/encounters/${ENCOUNTER}/notes`)).toBe(false);
    expect(isQueueable('PUT', `/encounters/xx/notes`)).toBe(false);
  });
});

describe('P3-02 antrean offline terenkripsi', () => {
  it('menyimpan isi terenkripsi, bukan teks asli, dan tidak mencantumkan isi pada daftar', async () => {
    const store = memoryStore();
    const queue = new OfflineQueue(store, crypto);

    const item = await queue.enqueue('U1', 'PUT', `/encounters/${ENCOUNTER}/notes`, {
      subjective: 'Nyeri gigi 46 sejak semalam',
    });

    expect(item).not.toBeNull();
    const stored = [...store.items.values()][0]!;
    expect(new TextDecoder().decode(stored.cipher)).not.toContain('Nyeri');
    expect(stored.iv.byteLength).toBe(12);
    const listed = await queue.list('U1');
    expect(listed).toHaveLength(1);
    expect(JSON.stringify(listed)).not.toContain('Nyeri');
    expect(listed[0]).toMatchObject({ label: 'Catatan SOAP', status: 'pending', userId: 'U1' });
    expect(await queue.decrypt(stored)).toEqual({ subjective: 'Nyeri gigi 46 sejak semalam' });
  });

  it('menolak mengantrekan aksi yang tidak diizinkan', async () => {
    const queue = new OfflineQueue(memoryStore(), crypto);
    expect(await queue.enqueue('U1', 'POST', '/invoices/X/payments', { amount: 1 })).toBeNull();
  });

  it('antrean hanya terlihat dan terkirim untuk pemiliknya', async () => {
    const queue = new OfflineQueue(memoryStore(), crypto);
    await queue.enqueue('U1', 'PUT', `/encounters/${ENCOUNTER}/notes`, { a: 1 });
    await queue.enqueue('U2', 'PUT', `/encounters/${ENCOUNTER}/anamnesis`, { b: 2 });

    expect(await queue.list('U1')).toHaveLength(1);
    const seen: unknown[] = [];
    const send: Sender = (_item, body) => {
      seen.push(body);
      return Promise.resolve({ kind: 'sent' });
    };
    expect(await queue.sync('U1', send)).toEqual({ sent: 1, flagged: 0, stopped: false });
    expect(seen).toEqual([{ a: 1 }]);
    expect(await queue.list('U2')).toHaveLength(1);
  });

  it('mengirim berurutan, berhenti saat jaringan putus, dan mempertahankan sisanya', async () => {
    const store = memoryStore();
    let now = 1000;
    const queue = new OfflineQueue(store, crypto, () => now);
    for (const body of [{ n: 1 }, { n: 2 }, { n: 3 }]) {
      now += 10;
      await queue.enqueue('U1', 'PUT', `/encounters/${ENCOUNTER}/notes`, body);
    }
    const order: number[] = [];
    const send: Sender = (_item, body) => {
      const n = (body as { n: number }).n;
      order.push(n);
      return Promise.resolve(n === 2 ? { kind: 'retry' } : { kind: 'sent' });
    };

    const result = await queue.sync('U1', send);

    expect(order).toEqual([1, 2]);
    expect(result).toEqual({ sent: 1, flagged: 0, stopped: true });
    const left = await queue.list('U1');
    expect(left).toHaveLength(2);
    expect(left[0]?.attempts).toBe(1);
  });

  it('konflik dan penolakan ditandai dan tidak dibuang; dapat dikirim ulang setelah ditinjau', async () => {
    const queue = new OfflineQueue(memoryStore(), crypto);
    await queue.enqueue('U1', 'PUT', `/encounters/${ENCOUNTER}/notes`, { n: 1 });
    await queue.enqueue('U1', 'PUT', `/encounters/${ENCOUNTER}/anamnesis`, { n: 2 });
    const outcomes = [
      { kind: 'conflict', message: 'Data berubah.' },
      { kind: 'rejected', message: 'Kunjungan terkunci.' },
    ] as const;
    let i = 0;
    const send: Sender = () => Promise.resolve(outcomes[i++]!);

    expect(await queue.sync('U1', send)).toEqual({ sent: 0, flagged: 2, stopped: false });

    const rows = await queue.list('U1');
    expect(rows.map((r) => r.status)).toEqual(['conflict', 'rejected']);
    expect(rows[0]?.message).toBe('Data berubah.');
    // Yang ditandai tidak dikirim otomatis lagi.
    const again = await queue.sync('U1', () => Promise.resolve({ kind: 'sent' }));
    expect(again.sent).toBe(0);

    await queue.retry(rows[0]!.id);
    expect((await queue.list('U1'))[0]?.status).toBe('pending');
    expect((await queue.sync('U1', () => Promise.resolve({ kind: 'sent' }))).sent).toBe(1);
  });

  it('membuang antrean yang melewati 72 jam', async () => {
    const store = memoryStore();
    let now = 0;
    const queue = new OfflineQueue(store, crypto, () => now);
    await queue.enqueue('U1', 'PUT', `/encounters/${ENCOUNTER}/notes`, { n: 1 });
    now = MAX_AGE_MS - 1;
    expect(await queue.list('U1')).toHaveLength(1);
    now = MAX_AGE_MS + 1;
    expect(await queue.list('U1')).toHaveLength(0);
    expect(store.items.size).toBe(0);
  });

  it('memakai satu kunci per perangkat', async () => {
    const store = memoryStore();
    const queue = new OfflineQueue(store, crypto);
    const a = await queue.enqueue('U1', 'PUT', `/encounters/${ENCOUNTER}/notes`, { n: 1 });
    const b = await queue.enqueue('U1', 'PUT', `/encounters/${ENCOUNTER}/notes`, { n: 2 });
    expect(a?.key).not.toBe(b?.key);
    expect(await queue.decrypt([...store.items.values()][1]!)).toEqual({ n: 2 });
  });
});
