import type { QueueStore, QueuedRequest } from '@/lib/offline/queue';

const DB_NAME = 'sikg-offline';
const REQUESTS = 'requests';
const KEYS = 'keys';
const KEY_ID = 'device';

function open(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, 1);
    request.onupgradeneeded = () => {
      const db = request.result;
      db.createObjectStore(REQUESTS, { keyPath: 'id' });
      db.createObjectStore(KEYS);
    };
    request.onsuccess = () => {
      resolve(request.result);
    };
    request.onerror = () => {
      reject(request.error ?? new Error('IndexedDB tidak dapat dibuka.'));
    };
  });
}

function run<T>(
  store: string,
  mode: IDBTransactionMode,
  action: (objectStore: IDBObjectStore) => IDBRequest<T>,
): Promise<T> {
  return open().then(
    (db) =>
      new Promise<T>((resolve, reject) => {
        const tx = db.transaction(store, mode);
        const request = action(tx.objectStore(store));
        request.onsuccess = () => {
          resolve(request.result);
        };
        request.onerror = () => {
          reject(request.error ?? new Error('Operasi IndexedDB gagal.'));
        };
        tx.oncomplete = () => {
          db.close();
        };
      }),
  );
}

/** Penyimpanan antrean di IndexedDB peramban. Kunci enkripsi tersimpan sebagai CryptoKey tak dapat diekspor. */
export const idbStore: QueueStore = {
  list: () =>
    run<QueuedRequest[]>(REQUESTS, 'readonly', (s) => s.getAll() as IDBRequest<QueuedRequest[]>),
  put: async (item) => {
    await run(REQUESTS, 'readwrite', (s) => s.put(item));
  },
  remove: async (id) => {
    await run(REQUESTS, 'readwrite', (s) => s.delete(id));
  },
  loadKey: async () => {
    const key = await run<CryptoKey | undefined>(
      KEYS,
      'readonly',
      (s) => s.get(KEY_ID) as IDBRequest<CryptoKey | undefined>,
    );
    return key ?? null;
  },
  saveKey: async (key) => {
    await run(KEYS, 'readwrite', (s) => s.put(key, KEY_ID));
  },
};
