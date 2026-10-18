import { queueLabel } from '@/lib/offline/allowlist';

/**
 * Antrean pencatatan saat offline (P3-02). Isi permintaan (data klinis) disimpan terenkripsi AES-GCM dengan kunci yang tidak dapat
 * diekspor dan hanya hidup di perangkat ini; antrean dihapus begitu terkirim dan berkas yang tertinggal melewati batas usia dibuang
 * (M11). Tidak ada pustaka tambahan: Web Crypto bawaan peramban.
 */

export const MAX_AGE_MS = 72 * 60 * 60 * 1000;

export type QueueStatus = 'pending' | 'conflict' | 'rejected';

export interface QueuedRequest {
  id: string;
  /** Kunci idempotensi dikirim ke server agar pengiriman ulang tidak mencatat dua kali. */
  key: string;
  method: 'PUT';
  url: string;
  label: string;
  userId: string;
  createdAt: number;
  status: QueueStatus;
  message: string | null;
  attempts: number;
  iv: ArrayBuffer;
  cipher: ArrayBuffer;
}

export interface QueueStore {
  list(): Promise<QueuedRequest[]>;
  put(item: QueuedRequest): Promise<void>;
  remove(id: string): Promise<void>;
  loadKey(): Promise<CryptoKey | null>;
  saveKey(key: CryptoKey): Promise<void>;
  /** Menghapus semua antrean dan kunci enkripsi dari penyimpanan. */
  clear(): Promise<void>;
}

export type SendOutcome =
  | { kind: 'sent' }
  | { kind: 'retry' }
  | { kind: 'conflict'; message: string }
  | { kind: 'rejected'; message: string };

export type Sender = (item: QueuedRequest, body: unknown) => Promise<SendOutcome>;

export class OfflineQueue {
  constructor(
    private readonly store: QueueStore,
    private readonly crypto: Crypto,
    private readonly now: () => number = () => Date.now(),
  ) {}

  private async key(): Promise<CryptoKey> {
    const existing = await this.store.loadKey();
    if (existing) {
      return existing;
    }
    const created = await this.crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, false, [
      'encrypt',
      'decrypt',
    ]);
    await this.store.saveKey(created);
    return created;
  }

  /**
   * Menambah satu permintaan. Mengembalikan null bila aksi itu tidak boleh diantrekan.
   */
  async enqueue(
    userId: string,
    method: string,
    url: string,
    body: unknown,
  ): Promise<QueuedRequest | null> {
    const label = queueLabel(method, url);
    if (label === null) {
      return null;
    }
    const iv = this.crypto.getRandomValues(new Uint8Array(12));
    const plain = new TextEncoder().encode(JSON.stringify(body));
    const cipher = await this.crypto.subtle.encrypt(
      { name: 'AES-GCM', iv },
      await this.key(),
      plain,
    );
    const item: QueuedRequest = {
      id: this.crypto.randomUUID(),
      key: this.crypto.randomUUID(),
      method: 'PUT',
      url,
      label,
      userId,
      createdAt: this.now(),
      status: 'pending',
      message: null,
      attempts: 0,
      iv: iv.buffer.slice(0),
      cipher,
    };
    await this.store.put(item);
    return item;
  }

  /** Daftar tanpa isi (aman ditampilkan); permintaan milik pengguna lain tidak ikut. */
  async list(userId: string): Promise<Omit<QueuedRequest, 'iv' | 'cipher'>[]> {
    await this.purgeExpired();
    const items = (await this.store.list()).filter((item) => item.userId === userId);
    return items
      .sort((a, b) => a.createdAt - b.createdAt)
      .map((item) => ({
        id: item.id,
        key: item.key,
        method: item.method,
        url: item.url,
        label: item.label,
        userId: item.userId,
        createdAt: item.createdAt,
        status: item.status,
        message: item.message,
        attempts: item.attempts,
      }));
  }

  async remove(id: string): Promise<void> {
    await this.store.remove(id);
  }

  /** Membuang semua antrean dan kunci enkripsi (dipakai saat keluar akun, supaya tidak ada data klinis tertinggal di perangkat). */
  async wipe(): Promise<void> {
    await this.store.clear();
  }

  /** Membuang antrean milik pengguna lain (perangkat bersama); kunci dibuang juga bila tidak ada lagi yang tersisa. */
  async purgeOtherUsers(userId: string): Promise<void> {
    const items = await this.store.list();
    for (const item of items) {
      if (item.userId !== userId) {
        await this.store.remove(item.id);
      }
    }
    if (items.length > 0 && items.every((item) => item.userId !== userId)) {
      await this.store.clear();
    }
  }

  /** Membuang berkas yang melewati batas usia. */
  async purgeExpired(): Promise<void> {
    const limit = this.now() - MAX_AGE_MS;
    for (const item of await this.store.list()) {
      if (item.createdAt < limit) {
        await this.store.remove(item.id);
      }
    }
  }

  async decrypt(item: QueuedRequest): Promise<unknown> {
    const plain = await this.crypto.subtle.decrypt(
      { name: 'AES-GCM', iv: item.iv },
      await this.key(),
      item.cipher,
    );
    return JSON.parse(new TextDecoder().decode(plain)) as unknown;
  }

  /**
   * Mengirim antrean milik pengguna berurutan. Berhenti pada galat jaringan (coba lagi nanti); konflik dan penolakan ditandai dan
   * tetap di antrean untuk ditinjau petugas, tidak pernah dibuang diam-diam.
   */
  async sync(
    userId: string,
    send: Sender,
  ): Promise<{ sent: number; flagged: number; stopped: boolean }> {
    await this.purgeExpired();
    const items = (await this.store.list())
      .filter((item) => item.userId === userId && item.status === 'pending')
      .sort((a, b) => a.createdAt - b.createdAt);
    let sent = 0;
    let flagged = 0;
    for (const item of items) {
      const outcome = await send(item, await this.decrypt(item));
      if (outcome.kind === 'retry') {
        await this.store.put({ ...item, attempts: item.attempts + 1 });
        return { sent, flagged, stopped: true };
      }
      if (outcome.kind === 'sent') {
        await this.store.remove(item.id);
        sent += 1;
        continue;
      }
      await this.store.put({
        ...item,
        status: outcome.kind,
        message: outcome.message,
        attempts: item.attempts + 1,
      });
      flagged += 1;
    }
    return { sent, flagged, stopped: false };
  }

  /** Mengembalikan permintaan yang ditandai ke status menunggu (setelah petugas memeriksa). */
  async retry(id: string): Promise<void> {
    const item = (await this.store.list()).find((candidate) => candidate.id === id);
    if (item) {
      await this.store.put({ ...item, status: 'pending', message: null });
    }
  }
}
