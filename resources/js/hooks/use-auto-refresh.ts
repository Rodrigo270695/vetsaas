import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

const DEFAULT_INTERVAL_MS = 15_000;

type Options = {
    only: string[];
    intervalMs?: number;
    preserveScroll?: boolean;
};

/**
 * Recarga parcial vía Inertia cada N segundos y expone contador "hace Xs".
 */
export function useAutoRefresh({
    only,
    intervalMs = DEFAULT_INTERVAL_MS,
    preserveScroll = true,
}: Options) {
    const [secondsSince, setSecondsSince] = useState(0);
    const [isRefreshing, setIsRefreshing] = useState(false);

    const refresh = useCallback(() => {
        setIsRefreshing(true);
        router.reload({
            only,
            preserveScroll,
            onFinish: () => {
                setIsRefreshing(false);
                setSecondsSince(0);
            },
        });
    }, [only, preserveScroll]);

    useEffect(() => {
        const intervalRef = window.setInterval(refresh, intervalMs);
        const tickRef = window.setInterval(() => setSecondsSince((s) => s + 1), 1_000);

        return () => {
            window.clearInterval(intervalRef);
            window.clearInterval(tickRef);
        };
    }, [refresh, intervalMs]);

    return { secondsSince, isRefreshing, refresh };
}
