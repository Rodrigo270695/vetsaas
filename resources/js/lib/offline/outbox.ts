import {
    idbDeleteOutbox,
    idbGetOutbox,
    idbListOutbox,
    idbPutOutbox,
} from './idb';
import { notifyPendingCountChanged } from './sync-coordinator';
import type { OutboxItem, OutboxStatus } from './types';

const syncingUuids = new Set<string>();

function localLabel(): string {
    const stamp = Date.now().toString(36).slice(-4).toUpperCase();

    return `OFF-${stamp}`;
}

export function notifyOutboxChanged(): void {
    notifyPendingCountChanged();
}

export function tryClaimSync(uuid: string): boolean {
    if (syncingUuids.has(uuid)) {
        return false;
    }

    syncingUuids.add(uuid);

    return true;
}

export function releaseSync(uuid: string): void {
    syncingUuids.delete(uuid);
}

export async function enqueueOutbox(
    type: OutboxItem['type'],
    payload: Record<string, unknown>,
): Promise<OutboxItem> {
    const item: OutboxItem = {
        uuid: crypto.randomUUID(),
        type,
        payload,
        status: 'pending',
        created_at: new Date().toISOString(),
        local_label: localLabel(),
    };

    await idbPutOutbox(item);
    notifyOutboxChanged();

    return item;
}

export async function listOutbox(): Promise<OutboxItem[]> {
    const rows = await idbListOutbox<OutboxItem>();

    return rows.sort((a, b) => a.created_at.localeCompare(b.created_at));
}

export async function countPendingOutbox(): Promise<number> {
    const rows = await listOutbox();

    return rows.filter((r) => r.status === 'pending' || r.status === 'failed').length;
}

export async function updateOutboxStatus(
    uuid: string,
    status: OutboxStatus,
    patch: Partial<OutboxItem> = {},
): Promise<void> {
    const current = await idbGetOutbox<OutboxItem>(uuid);

    if (!current) {
        return;
    }

    await idbPutOutbox({
        ...current,
        ...patch,
        status,
        synced_at: status === 'synced' ? new Date().toISOString() : current.synced_at,
    });
    notifyOutboxChanged();
}

export async function removeSyncedOutbox(uuid: string): Promise<void> {
    await idbDeleteOutbox(uuid);
    notifyOutboxChanged();
}

export async function listPendingOutbox(): Promise<OutboxItem[]> {
    const rows = await listOutbox();

    return rows.filter((r) => r.status === 'pending' || r.status === 'failed');
}

export async function listPendingOutboxForDisplay(): Promise<OutboxItem[]> {
    return listPendingOutbox();
}
