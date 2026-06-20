import type { OutboxItem } from './types';

type FlushResult = { synced: number; failed: number };

type Listener = () => void;

let flushPromise: Promise<FlushResult> | null = null;
let pendingCountListeners = new Set<Listener>();

export function subscribePendingCount(listener: Listener): () => void {
    pendingCountListeners.add(listener);

    return () => pendingCountListeners.delete(listener);
}

export function notifyPendingCountChanged(): void {
    pendingCountListeners.forEach((listener) => listener());
}

/**
 * Motor único de sincronización. Evita flushes paralelos que duplicaban ventas.
 */
export async function coordinatedFlush(
    flushFn: () => Promise<FlushResult>,
): Promise<FlushResult> {
    if (flushPromise) {
        return flushPromise;
    }

    flushPromise = flushFn().finally(() => {
        flushPromise = null;
        notifyPendingCountChanged();
    });

    return flushPromise;
}

export function isFlushRunning(): boolean {
    return flushPromise !== null;
}

export type { OutboxItem, FlushResult };
