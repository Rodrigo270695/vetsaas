import { useCallback, useEffect, useState } from 'react';
import { isIndexedDbSupported } from '@/lib/offline/idb';
import { getOutboxSummary, listOutbox, resetStuckSyncingOutbox } from '@/lib/offline/outbox';
import { subscribePendingCount } from '@/lib/offline/sync-coordinator';
import type { OutboxItem } from '@/lib/offline/types';

export function useOutboxQueue() {
    const [items, setItems] = useState<OutboxItem[]>([]);
    const [summary, setSummary] = useState({
        pending: 0,
        syncing: 0,
        failed: 0,
        total: 0,
    });
    const [supported, setSupported] = useState(true);
    const [loading, setLoading] = useState(true);

    const refresh = useCallback(async () => {
        if (!isIndexedDbSupported()) {
            setSupported(false);
            setItems([]);
            setSummary({ pending: 0, syncing: 0, failed: 0, total: 0 });
            setLoading(false);

            return;
        }

        setSupported(true);
        await resetStuckSyncingOutbox();
        const [rows, stats] = await Promise.all([listOutbox(), getOutboxSummary()]);
        setItems(rows.sort((a, b) => b.created_at.localeCompare(a.created_at)));
        setSummary(stats);
        setLoading(false);
    }, []);

    useEffect(() => {
        void refresh();

        return subscribePendingCount(() => {
            void refresh();
        });
    }, [refresh]);

    return {
        items,
        summary,
        supported,
        loading,
        refresh,
    };
}
