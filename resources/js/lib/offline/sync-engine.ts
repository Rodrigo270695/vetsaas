import { coordinatedFlush } from './sync-coordinator';
import { offlineFetchJson } from './api';
import {
    countPendingOutbox,
    listOutbox,
    listPendingOutbox,
    notifyOutboxChanged,
    removeSyncedOutbox,
    tryClaimSync,
    releaseSync,
    updateOutboxStatus,
} from './outbox';
import type { OutboxItem, SyncPushResult } from './types';

export type PushOutboxResult = 'synced' | 'failed' | 'offline' | 'busy';

async function pushOutboxItem(item: OutboxItem): Promise<PushOutboxResult> {
    if (!navigator.onLine) {
        return 'offline';
    }

    if (!tryClaimSync(item.uuid)) {
        return 'busy';
    }

    await updateOutboxStatus(item.uuid, 'syncing');

    try {
        const response = await offlineFetchJson<{ results: SyncPushResult[] }>(
            '/offline/sync/push',
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

            return 'failed';
        }

        await removeSyncedOutbox(item.uuid);

        return 'synced';
    } catch {
        await updateOutboxStatus(item.uuid, 'pending');

        return 'failed';
    } finally {
        releaseSync(item.uuid);
    }
}

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
        const result = await pushOutboxItem(item);

        if (result === 'synced') {
            synced += 1;
        } else if (result === 'failed') {
            failed += 1;
        }
    }

    notifyOutboxChanged();

    return { synced, failed };
}

export async function flushOfflineOutbox(): Promise<{ synced: number; failed: number }> {
    return coordinatedFlush(flushOfflineOutboxInternal);
}

export async function syncSingleOutboxItem(uuid: string): Promise<PushOutboxResult> {
    let outcome: PushOutboxResult = 'failed';

    await coordinatedFlush(async () => {
        const rows = await listOutbox();
        const item = rows.find((row) => row.uuid === uuid);

        if (!item) {
            outcome = 'failed';

            return { synced: 0, failed: 0 };
        }

        if (item.status === 'failed') {
            await updateOutboxStatus(uuid, 'pending', { error: undefined });
        }

        const fresh = (await listOutbox()).find((row) => row.uuid === uuid);

        if (!fresh || fresh.status === 'syncing') {
            outcome = 'busy';

            return { synced: 0, failed: 0 };
        }

        if (fresh.status !== 'pending') {
            outcome = 'failed';

            return { synced: 0, failed: 0 };
        }

        outcome = await pushOutboxItem(fresh);
        notifyOutboxChanged();

        return {
            synced: outcome === 'synced' ? 1 : 0,
            failed: outcome === 'failed' ? 1 : 0,
        };
    });

    return outcome;
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
