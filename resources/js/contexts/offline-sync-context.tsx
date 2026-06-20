import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import { router } from '@inertiajs/react';
import { fetchCajaBootstrap } from '@/lib/offline/api';
import { CAJA_OFFLINE_PATHS } from '@/lib/offline/caja-routes';
import { saveCajaBootstrap } from '@/lib/offline/cache';
import { isIndexedDbSupported } from '@/lib/offline/idb';
import { flushOfflineOutbox, getPendingSummary } from '@/lib/offline/sync-engine';
import { subscribePendingCount } from '@/lib/offline/sync-coordinator';

type OfflineSyncContextValue = {
    isOnline: boolean;
    pendingCount: number;
    isSyncing: boolean;
    syncNow: () => Promise<void>;
    refreshPending: () => Promise<void>;
};

const OfflineSyncContext = createContext<OfflineSyncContextValue | null>(null);

function prefetchCajaWithInertia(): void {
    for (const path of CAJA_OFFLINE_PATHS) {
        router.prefetch(path);
    }
}

export function OfflineSyncProvider({ children }: { children: ReactNode }) {
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
        void refreshPending();

        return subscribePendingCount(() => {
            void refreshPending();
        });
    }, [refreshPending]);

    useEffect(() => {
        const onOnline = () => {
            setIsOnline(true);
            void syncNow();
            void fetchCajaBootstrap()
                .then((data) => saveCajaBootstrap(data as never))
                .catch(() => undefined);
            prefetchCajaWithInertia();
        };
        const onOffline = () => setIsOnline(false);

        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);

        if (navigator.onLine) {
            void fetchCajaBootstrap()
                .then((data) => saveCajaBootstrap(data as never))
                .catch(() => undefined);
            prefetchCajaWithInertia();
        }

        return () => {
            window.removeEventListener('online', onOnline);
            window.removeEventListener('offline', onOffline);
        };
    }, [syncNow]);

    const value = useMemo(
        () => ({
            isOnline,
            pendingCount,
            isSyncing,
            syncNow,
            refreshPending,
        }),
        [isOnline, pendingCount, isSyncing, syncNow, refreshPending],
    );

    return (
        <OfflineSyncContext.Provider value={value}>
            {children}
        </OfflineSyncContext.Provider>
    );
}

export function useOfflineSync(): OfflineSyncContextValue {
    const ctx = useContext(OfflineSyncContext);

    if (!ctx) {
        throw new Error('useOfflineSync debe usarse dentro de OfflineSyncProvider');
    }

    return ctx;
}
