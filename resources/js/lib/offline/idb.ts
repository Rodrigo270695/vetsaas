const DB_NAME = 'vetsaas-offline';
const DB_VERSION = 1;

type StoreName = 'outbox' | 'cache';

function openDb(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;

            if (!db.objectStoreNames.contains('outbox')) {
                const outbox = db.createObjectStore('outbox', { keyPath: 'uuid' });
                outbox.createIndex('status', 'status', { unique: false });
                outbox.createIndex('created_at', 'created_at', { unique: false });
            }

            if (!db.objectStoreNames.contains('cache')) {
                db.createObjectStore('cache', { keyPath: 'key' });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error ?? new Error('IndexedDB open failed'));
    });
}

function runTransaction<T>(
    storeName: StoreName,
    mode: IDBTransactionMode,
    run: (store: IDBObjectStore) => Promise<T> | T,
): Promise<T> {
    return openDb().then(
        (db) =>
            new Promise((resolve, reject) => {
                const tx = db.transaction(storeName, mode);
                const store = tx.objectStore(storeName);

                Promise.resolve(run(store))
                    .then(resolve)
                    .catch(reject);

                tx.onerror = () => reject(tx.error ?? new Error('IndexedDB tx failed'));
            }),
    );
}

export async function idbGet<T>(key: string): Promise<T | null> {
    return runTransaction('cache', 'readonly', (store) =>
        new Promise<T | null>((resolve, reject) => {
            const req = store.get(key);
            req.onsuccess = () => {
                const row = req.result as { key: string; value: T } | undefined;
                resolve(row?.value ?? null);
            };
            req.onerror = () => reject(req.error);
        }),
    );
}

export async function idbSet<T>(key: string, value: T): Promise<void> {
    await runTransaction('cache', 'readwrite', (store) =>
        new Promise<void>((resolve, reject) => {
            const req = store.put({ key, value });
            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        }),
    );
}

export async function idbPutOutbox<T extends { uuid: string }>(item: T): Promise<void> {
    await runTransaction('outbox', 'readwrite', (store) =>
        new Promise<void>((resolve, reject) => {
            const req = store.put(item);
            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        }),
    );
}

export async function idbGetOutbox<T extends { uuid: string }>(uuid: string): Promise<T | null> {
    return runTransaction('outbox', 'readonly', (store) =>
        new Promise<T | null>((resolve, reject) => {
            const req = store.get(uuid);
            req.onsuccess = () => resolve((req.result as T | undefined) ?? null);
            req.onerror = () => reject(req.error);
        }),
    );
}

export async function idbListOutbox<T>(): Promise<T[]> {
    return runTransaction('outbox', 'readonly', (store) =>
        new Promise<T[]>((resolve, reject) => {
            const req = store.getAll();
            req.onsuccess = () => resolve((req.result as T[]) ?? []);
            req.onerror = () => reject(req.error);
        }),
    );
}

export async function idbDeleteOutbox(uuid: string): Promise<void> {
    await runTransaction('outbox', 'readwrite', (store) =>
        new Promise<void>((resolve, reject) => {
            const req = store.delete(uuid);
            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        }),
    );
}

export function isIndexedDbSupported(): boolean {
    return typeof indexedDB !== 'undefined';
}
