import { useCallback, useRef } from 'react';
import type { PointerEvent } from 'react';

type Options = {
    thresholdPx?: number;
    maxVerticalPx?: number;
};

/**
 * Swipe horizontal (→) para responder, estilo WhatsApp.
 * No dispara si el movimiento es más vertical (scroll).
 */
export function useSwipeToReply(
    onReply: () => void,
    { thresholdPx = 72, maxVerticalPx = 40 }: Options = {},
) {
    const startRef = useRef<{ x: number; y: number } | null>(null);
    const firedRef = useRef(false);

    const onPointerDown = useCallback((e: PointerEvent) => {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        firedRef.current = false;
        startRef.current = { x: e.clientX, y: e.clientY };
    }, []);

    const onPointerMove = useCallback(
        (e: PointerEvent) => {
            const start = startRef.current;
            if (!start || firedRef.current) return;
            const dx = e.clientX - start.x;
            const dy = Math.abs(e.clientY - start.y);
            if (dy > maxVerticalPx) {
                startRef.current = null;
                return;
            }
            if (dx >= thresholdPx) {
                firedRef.current = true;
                startRef.current = null;
                try {
                    navigator.vibrate?.(10);
                } catch {
                    // ignore
                }
                onReply();
            }
        },
        [maxVerticalPx, onReply, thresholdPx],
    );

    const onPointerUp = useCallback(() => {
        startRef.current = null;
    }, []);

    const onPointerCancel = useCallback(() => {
        startRef.current = null;
    }, []);

    return {
        onPointerDown,
        onPointerMove,
        onPointerUp,
        onPointerCancel,
    };
}
