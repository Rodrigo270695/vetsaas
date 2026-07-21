import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

const DEFAULT_INTERVAL_MS = 15_000;

type Options = {
    only: string[];
    intervalMs?: number;
    /** Si es false, no hace polling (p. ej. asistente apagado). */
    enabled?: boolean;
    /** Evita competir con otra navegación o filtro que ya esté cargando. */
    busy?: boolean;
};

/**
 * Recarga parcial vía Inertia cada N segundos y expone contador "hace Xs".
 */
export function useAutoRefresh({
    only,
    intervalMs = DEFAULT_INTERVAL_MS,
    enabled = true,
    busy = false,
}: Options) {
    const [secondsSince, setSecondsSince] = useState(0);
    const [isRefreshing, setIsRefreshing] = useState(false);

    const onlyRef = useRef(only);
    const busyRef = useRef(busy);
    const isRefreshingRef = useRef(false);

    useEffect(() => {
        onlyRef.current = only;
    }, [only]);

    useEffect(() => {
        busyRef.current = busy;
    }, [busy]);

    const refresh = useCallback(() => {
        if (
            !enabled
            || busyRef.current
            || isRefreshingRef.current
            || document.visibilityState !== 'visible'
        ) {
            return;
        }

        isRefreshingRef.current = true;
        setIsRefreshing(true);
        setSecondsSince(0);

        router.reload({
            only: onlyRef.current,
            onFinish: () => {
                isRefreshingRef.current = false;
                setIsRefreshing(false);
                setSecondsSince(0);
            },
        });
    }, [enabled]);

    useEffect(() => {
        if (!enabled) {
            isRefreshingRef.current = false;
            setIsRefreshing(false);
            setSecondsSince(0);
            return;
        }

        const refreshInterval = window.setInterval(refresh, intervalMs);
        const tickInterval = window.setInterval(() => {
            if (!isRefreshingRef.current) {
                setSecondsSince((s) => s + 1);
            }
        }, 1_000);

        return () => {
            window.clearInterval(refreshInterval);
            window.clearInterval(tickInterval);
        };
    }, [enabled, intervalMs, refresh]);

    return { secondsSince, isRefreshing, refresh };
}
