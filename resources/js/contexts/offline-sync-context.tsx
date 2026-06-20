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
import { fetchOfflineBootstrap } from '@/lib/offline/api';
import { saveCajaBootstrap, saveClinicaBootstrap, saveConfiguracionBootstrap, saveInventarioBootstrap, saveServiciosBootstrap } from '@/lib/offline/cache';
import { isIndexedDbSupported } from '@/lib/offline/idb';
import { OFFLINE_PATHS } from '@/lib/offline/offline-routes';
import { flushOfflineOutbox, getPendingSummary } from '@/lib/offline/sync-engine';
import { subscribePendingCount } from '@/lib/offline/sync-coordinator';
import type { CajaBootstrapCache, ClinicaBootstrapCache, ConfiguracionBootstrapCache, InventarioBootstrapCache, ServiciosBootstrapCache } from '@/lib/offline/types';

type OfflineSyncContextValue = {
    isOnline: boolean;
    pendingCount: number;
    isSyncing: boolean;
    syncNow: () => Promise<void>;
    refreshPending: () => Promise<void>;
};

const OfflineSyncContext = createContext<OfflineSyncContextValue | null>(null);

function prefetchOfflineWithInertia(): void {
    for (const path of OFFLINE_PATHS) {
        router.prefetch(path);
    }
}

async function refreshBootstrapCaches(): Promise<void> {
    await Promise.all([
        fetchOfflineBootstrap('caja')
            .then((data) => saveCajaBootstrap(data as CajaBootstrapCache))
            .catch(() => undefined),
        fetchOfflineBootstrap('clinica')
            .then((data) => saveClinicaBootstrap(data as ClinicaBootstrapCache))
            .catch(() => undefined),
        fetchOfflineBootstrap('inventario')
            .then((data) => saveInventarioBootstrap(data as InventarioBootstrapCache))
            .catch(() => undefined),
        fetchOfflineBootstrap('servicios')
            .then((data) => saveServiciosBootstrap(data as ServiciosBootstrapCache))
            .catch(() => undefined),
        fetchOfflineBootstrap('configuracion')
            .then((data) => saveConfiguracionBootstrap(data as ConfiguracionBootstrapCache))
            .catch(() => undefined),
    ]);
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
            await refreshBootstrapCaches();
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
            void refreshBootstrapCaches();
            prefetchOfflineWithInertia();
        };
        const onOffline = () => setIsOnline(false);

        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);

        if (navigator.onLine) {
            void refreshBootstrapCaches();
            prefetchOfflineWithInertia();
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
