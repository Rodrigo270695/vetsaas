import { enqueueOutbox } from './outbox';
import { isIndexedDbSupported } from './idb';
import type { OutboxItem, OutboxType } from './types';
import { toastManager } from '@/lib/toast';

export function isOfflineMode(): boolean {
    return typeof navigator !== 'undefined' && !navigator.onLine && isIndexedDbSupported();
}

export async function enqueueIfOffline(
    type: OutboxType,
    payload: Record<string, unknown>,
    options: {
        refreshPending: () => Promise<void>;
        onSuccess: () => void;
        title: string;
        description: string;
    },
): Promise<OutboxItem | null> {
    if (!isOfflineMode()) {
        return null;
    }

    try {
        const item = await enqueueOutbox(type, payload);
        await options.refreshPending();
        toastManager.success({
            title: options.title,
            description: options.description.replace(
                '{{label}}',
                item.local_label ?? item.uuid.slice(0, 8),
            ),
        });
        options.onSuccess();

        return item;
    } catch {
        return null;
    }
}
