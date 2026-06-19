import { useCallback, useEffect, useState } from 'react';
import { fetchCajaBootstrap } from '@/lib/offline/api';
import { saveCajaBootstrap } from '@/lib/offline/cache';
import { isIndexedDbSupported } from '@/lib/offline/idb';
import { flushOfflineOutbox, getPendingSummary } from '@/lib/offline/sync-engine';

type OfflineSyncState = {
    isOnline: boolean;
    pendingCount: number;
    isSyncing: boolean;
};

export function useOfflineSync(): OfflineSyncState & {
    syncNow: () => Promise<void>;
    refreshPending: () => Promise<void>;
} {
    const [isOnline, setIsOnline] = useState(
        typeof navigator !== 'undefined' ? navigator.onLine : true,
    );
    const [pendingCount, setPendingCount] = useState(0);
    const [isSyncing, setIsSyncing] = useState(false);

    const refreshPending = useCallback(async () => {
        if (!isIndexedDbSupported()) {
            setPendingCount(0);

            return;
        }

        const summary = await getPendingSummary();
        setPendingCount(summary.count);
    }, []);

    const syncNow = useCallback(async () => {
        if (!navigator.onLine || !isIndexedDbSupported()) {
            return;
        }

        setIsSyncing(true);

        try {
            await flushOfflineOutbox();
            await refreshPending();
        } finally {
            setIsSyncing(false);
        }
    }, [refreshPending]);

    useEffect(() => {
        const onOnline = () => {
            setIsOnline(true);
            void syncNow();
            void fetchCajaBootstrap()
                .then((data) => saveCajaBootstrap(data as never))
                .catch(() => undefined);
        };
        const onOffline = () => setIsOnline(false);

        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);

        void refreshPending();

        return () => {
            window.removeEventListener('online', onOnline);
            window.removeEventListener('offline', onOffline);
        };
    }, [refreshPending, syncNow]);

    useEffect(() => {
        if (!navigator.onLine) {
            return;
        }

        void fetchCajaBootstrap()
            .then((data) => saveCajaBootstrap(data as never))
            .catch(() => undefined);
    }, []);

    return {
        isOnline,
        pendingCount,
        isSyncing,
        syncNow,
        refreshPending,
    };
}
