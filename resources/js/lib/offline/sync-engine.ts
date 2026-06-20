import { coordinatedFlush } from './sync-coordinator';
import { offlineFetchJson } from './api';
import {
    countPendingOutbox,
    listPendingOutbox,
    notifyOutboxChanged,
    removeSyncedOutbox,
    tryClaimSync,
    releaseSync,
    updateOutboxStatus,
} from './outbox';
import type { OutboxItem, SyncPushResult } from './types';

async function flushOfflineOutboxInternal(): Promise<{ synced: number; failed: number }> {
    if (!navigator.onLine) {
        return { synced: 0, failed: 0 };
    }

    const pending = await listPendingOutbox();

    if (pending.length === 0) {
        return { synced: 0, failed: 0 };
    }

    let synced = 0;
    let failed = 0;

    for (const item of pending) {
        if (!tryClaimSync(item.uuid)) {
            continue;
        }

        await updateOutboxStatus(item.uuid, 'syncing');

        try {
            const response = await offlineFetchJson<{ results: SyncPushResult[] }>(
                '/caja/offline/sync/push',
                {
                    method: 'POST',
                    body: JSON.stringify({
                        items: [
                            {
                                uuid: item.uuid,
                                type: item.type,
                                payload: item.payload,
                            },
                        ],
                    }),
                },
            );

            const result = response.results[0];

            if (!result || result.status === 'failed') {
                await updateOutboxStatus(item.uuid, 'failed', {
                    error: result?.error ?? 'Sync failed',
                });
                failed += 1;
                continue;
            }

            await removeSyncedOutbox(item.uuid);
            synced += 1;
        } catch {
            await updateOutboxStatus(item.uuid, 'pending');
            failed += 1;
        } finally {
            releaseSync(item.uuid);
        }
    }

    notifyOutboxChanged();

    return { synced, failed };
}

export async function flushOfflineOutbox(): Promise<{ synced: number; failed: number }> {
    return coordinatedFlush(flushOfflineOutboxInternal);
}

export async function getPendingSummary(): Promise<{
    count: number;
    items: OutboxItem[];
}> {
    const items = await listPendingOutbox();

    return {
        count: await countPendingOutbox(),
        items,
    };
}
