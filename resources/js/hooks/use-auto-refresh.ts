import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

const DEFAULT_INTERVAL_MS = 15_000;

type Options = {
    only: string[];
    intervalMs?: number;
    preserveScroll?: boolean;
    /** Si es false, no hace polling (p. ej. asistente apagado). */
    enabled?: boolean;
};

/**
 * Recarga parcial vía Inertia cada N segundos y expone contador "hace Xs".
 */
export function useAutoRefresh({
    only,
    intervalMs = DEFAULT_INTERVAL_MS,
    preserveScroll = true,
    enabled = true,
}: Options) {
    const [secondsSince, setSecondsSince] = useState(0);
    const [isRefreshing, setIsRefreshing] = useState(false);

    const onlyRef = useRef(only);
    const preserveScrollRef = useRef(preserveScroll);
    const isRefreshingRef = useRef(false);

    onlyRef.current = only;
    preserveScrollRef.current = preserveScroll;

    const refresh = useCallback(() => {
        if (!enabled || isRefreshingRef.current) {
            return;
        }

        isRefreshingRef.current = true;
        setIsRefreshing(true);
        setSecondsSince(0);

        router.reload({
            only: onlyRef.current,
            preserveScroll: preserveScrollRef.current,
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
