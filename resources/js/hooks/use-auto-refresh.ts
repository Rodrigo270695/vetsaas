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
 * Recarga parcial vía Inertia cada N segundos (desde que termina la anterior)
 * y expone contador "hace Xs".
 */
export function useAutoRefresh({
    only,
    intervalMs = DEFAULT_INTERVAL_MS,
    preserveScroll = true,
    enabled = true,
}: Options) {
    const [secondsSince, setSecondsSince] = useState(0);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const isRefreshingRef = useRef(false);

    const refresh = useCallback(() => {
        if (!enabled || isRefreshingRef.current) {
            return;
        }

        isRefreshingRef.current = true;
        setIsRefreshing(true);
        setSecondsSince(0);

        router.reload({
            only,
            preserveScroll,
            onFinish: () => {
                isRefreshingRef.current = false;
                setIsRefreshing(false);
                setSecondsSince(0);
            },
        });
    }, [only, preserveScroll, enabled]);

    useEffect(() => {
        if (!enabled) {
            isRefreshingRef.current = false;
            setIsRefreshing(false);
            setSecondsSince(0);
            return;
        }

        let cancelled = false;
        let refreshTimeout: ReturnType<typeof setTimeout> | null = null;
        let tickInterval: ReturnType<typeof setInterval> | null = null;

        const runRefreshCycle = () => {
            if (cancelled || isRefreshingRef.current) {
                return;
            }

            isRefreshingRef.current = true;
            setIsRefreshing(true);
            setSecondsSince(0);

            router.reload({
                only,
                preserveScroll,
                onFinish: () => {
                    if (cancelled) {
                        return;
                    }
                    isRefreshingRef.current = false;
                    setIsRefreshing(false);
                    setSecondsSince(0);
                    refreshTimeout = setTimeout(runRefreshCycle, intervalMs);
                },
            });
        };

        tickInterval = setInterval(() => {
            if (!isRefreshingRef.current) {
                setSecondsSince((s) => s + 1);
            }
        }, 1_000);

        refreshTimeout = setTimeout(runRefreshCycle, intervalMs);

        return () => {
            cancelled = true;
            if (refreshTimeout) {
                clearTimeout(refreshTimeout);
            }
            if (tickInterval) {
                clearInterval(tickInterval);
            }
        };
    }, [enabled, intervalMs, only, preserveScroll]);

    return { secondsSince, isRefreshing, refresh };
}
